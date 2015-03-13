<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2015 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2015 CDC Leuphana University Lueneburg
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/** Use the Internet Archive account and S3 APIs. */
class InternetArchive
{
    private $baseUrl = 'http://archive.org/';
    private $loginEndpoint;
    private $s3KeysEndpoint;

    /** Construct an InternetArchive object. */
    function __construct($config)
    {
        $this->loginEndpoint = $this->baseUrl . 'account/login.php';
        $this->s3KeysEndpoint = $this->baseUrl . 'account/s3.php';
        $this->userAgent = $config['http_client_user_agent'];
    }


    /**
     * Issue an HTTP GET request using IA credentials.
     *
     * @param string $endpoint The web service API URL
     * @param string $username The IA username
     * @param string $signature The signature from IA credentials
     * @param mixed $params Additional data to put into the query string
     * @return string HTTP response body
     */
    public function get($endpoint, $username = null, $signature = null, $params = null)
    {
        $params = ($params === null)? [] : $params;
        $params['output_json'] = '1';
        $url = $endpoint . '?' . http_build_query($params);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (!empty($username) && !empty($signature))
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Cookie: logged-in-user=$username; logged-in-sig=$signature"]);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        if (!$response)
            throw new Exception('cURL Error: ' . curl_error($curl));
        return $response;
    }

    /**
     * Issue an HTTP POST request using IA credentials.
     *
     * @param string $endpoint The web service API URL
     * @param array $data Associative array of POST values
     * @param string $username The IA username
     * @param string $signature The signature from IA credentials
     * @param mixed $params Additional data to put into the query string
     * @return string HTTP response body
     */
    public function post($endpoint, $data, $username = null, $signature = null, $params = null)
    {
        $params = ($params === null)? [] : $params;
        $url = $endpoint . '?' . http_build_query($params);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (!empty($username) && !empty($signature))
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Cookie: logged-in-user=$username; logged-in-sig=$signaure"]);
        else
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Cookie: test-cookie=1']);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        if (!$response)
            throw new Exception('cURL Error: ' . curl_error($curl));
        return $response;
    }

    /**
     * Authenticate with Internet Archive by password.
     *
     * @param string $username The IA username
     * @param string $password The IA account password
     * @return string|false The signature of the IA credentials
     */
    public function login($username, $password)
    {
        $response = $this->post($this->loginEndpoint, [
            'username' => $username,
            'password' => $password,
            'action'   => 'login'
        ]);
        preg_match_all('/Set-Cookie: (.*)\b/', $response, $cookies);
        $signature = false;
        foreach ($cookies[1] as $cookie) {
            $items = explode(';', $cookie);
            $pair = explode('=', $items[0]);
            if ($pair[0] === 'logged-in-sig')
                $signature = $pair[1];
        }
        return $signature;
    }

    /**
     * Fetch the IAS3 keys needed to upload and write items and files.
     *
     * @param string $username The IA username
     * @param string $The signature of the IA credentials
     * @return array Associative array decoded from the JSON response
     */
    public function getS3Keys($username, $signature)
    {
        return json_decode($this->get($this->s3KeysEndpoint, $username, $signature), $assoc = true);
    }

    static public function test($username, $password)
    {
        $config = ['http_client_user_agent' => config_item('http_client_user_agent')];
        $IA = new InternetArchive($config);
        $signature = $IA->login($username, $password);
        if ($signature) {
            $result = $IA->getS3Keys($username, $signature);
            if ($result['success']) {
                $key = $result['key'];
                echo "S3 Access Key: $key[s3accesskey]\n";
                echo "S3 Secret Key: $key[s3secretkey]\n";
            } else {
                echo "Fetching S3 keys failed.\n";
            }
        } else {
            echo "Login failed\n";
        }
    }
}
