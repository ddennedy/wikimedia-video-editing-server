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

require BASEPATH.'../vendor/autoload.php';
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Request;

/** Use the Internet Archive account and S3 APIs. */
class InternetArchive extends GuzzleHttp\Client
{
    const loginEndpoint = '/account/login.php';
    const s3endpoint = 'http://s3.us.archive.org';
    const s3KeysEndpoint = '/account/s3.php';
    const s3ItemPrefix = '/videoeditserver-';
    const downloadEndpoint = 'http://archive.org/download';

    /** Construct an InternetArchive object. */
    function __construct($config)
    {
        // Set default Guzze HTTP client options.
        parent::__construct([
            'base_url' => 'https://archive.org',
            'defaults' => [
                'debug' => false,
                'verify' => false,
                'headers' => [
                    'User-Agent' => $config['http_client_user_agent']
                ]
            ]
        ]);
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
        $response = $this->post(self::loginEndpoint, [
            'cookies' => ['test-cookie' => 1],
            'allow_redirects' => false,
            'body' => [
                'username' => $username,
                'password' => $password,
                'action'   => 'login'
            ]
        ]);
        $cookies = Request::parseHeader($response, 'Set-Cookie');
        $signature = false;
        foreach ($cookies as $cookie) {
            if (array_key_exists('logged-in-sig', $cookie)) {
                $signature = $cookie['logged-in-sig'];
                break;
            }
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
        $response = $this->get(self::s3KeysEndpoint, [
            'query' => ['output_json' => 1],
            'cookies' => [
                'logged-in-user' => $username,
                'logged-in-sig' => $signature
            ]
        ]);
        return $response->json();
    }

    public function testLogin($username, $password)
    {
        $signature = $this->login($username, $password);
        if ($signature) {
            $result = $this->getS3Keys($username, $signature);
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

    public function testGuzzle($accessKey, $secret)
    {
        $path = config_item('upload_path') . '00018.MTS';
        $url = self::s3endpoint . self::s3ItemPrefix .'/'. basename($path);
        $response = $this->put($url, [
            'headers' => [
                'x-amz-auto-make-bucket' => '1',
                'x-archive-meta01-collection' => 'test_collection',
                'x-archive-meta-mediatype' => 'movies',
                'x-archive-meta-title' => 'VideoEditServer Dev Test 1',
                'authorization' => "LOW $accessKey:$secret"
            ],
            'body' => fopen($path, 'r')
        ]);
        return $response;
    }

    /**
     * Generate the S3 item URL.
     *
     * @param string $path The file name or path being uploaded - only the base name is used
     * @param int The file record ID
     * @return string
     */
    protected function makeURL($path, $id)
    {
        $base = basename($path);
        return self::s3endpoint . self::s3ItemPrefix . "$id/$base";
    }

    /**
     * Get the Internet Archive media type.
     *
     * @param string $path The file name - only the extension is used
     * @param string $mimeType The MIME type
     * @return string
     */
    protected function getMediaType($path, $mimeType)
    {
        $mediaType = 'data';
        $majorType = explode('/', $mimeType)[0];
        switch ($majorType) {
            case 'video':
                $mediaType = 'movies';
                break;
            case 'audio':
            case 'image':
                $mediaType = $majorType;
                break;
            default:
                $CI =& get_instance();
                $CI->load->helper('path');
                $mediaType = (getExtension($path) == 'svg')? 'image' : 'data';
                break;
        }
        return $mediaType;
    }

    /**
     * Get the Internet Archive collection.
     *
     * @param string $mediaType An Internet Archive media type
     * @return string
     */
    protected function getCollection($mediaType)
    {
        //return 'test_collection';
        switch ($mediaType) {
            case 'audio':
                return 'opensource_audio';
            case 'image':
                return 'image';
            case 'movies':
                return 'opensource_movies';
            default:
                return 'data';
        }
    }

    /**
     * Convert the ISO 639-1 language code to MARC21 language code.
     *
     * @todo Expand the list as languages are added to the file form.
     * @param string $iso639 The ISO 639-1 language code
     * @return string
     */
    protected function getMARC21($iso639)
    {
        return element($iso639, [
            'en' => 'eng',
            'de' => 'ger'
        ]);
    }

    /**
     * Get a URL for a Wikimedia license code.
     *
     * @todo For multi-license the first thing besides "self" (which is really for
     * copyright) is used - rather arbitrarily.
     * @param string @license The Wikimedia license code
     * @return string
     */
    protected function getLicenseURL($license)
    {
        return element($license, [
            'self|GFDL|cc-by-sa-all|migration=redundant' => 'https://www.gnu.org/licenses/fdl-1.3.html',
            'self|Cc-zero' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'PD-self' => 'https://creativecommons.org/licenses/publicdomain/',
            'self|GFDL|cc-by-sa-3.0|migration=redundant' => 'https://www.gnu.org/licenses/fdl-1.3.html',
            'self|GFDL|cc-by-3.0|migration=redundant' => 'https://www.gnu.org/licenses/fdl-1.3.html',
            'self|cc-by-sa-3.0' => 'https://creativecommons.org/licenses/by-sa/3.0/',
            'cc-by-sa-4.0' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'cc-by-sa-3.0' => 'https://creativecommons.org/licenses/by-sa/3.0/',
            'cc-by-4.0' => 'https://creativecommons.org/licenses/by/4.0/',
            'cc-by-3.0' => 'https://creativecommons.org/licenses/by/3.0/',
            'Cc-zero' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'FAL' => 'https://artlibre.org/licence/lal/',
            'PD-old-100' => 'https://creativecommons.org/licenses/publicdomain/',
            'PD-old-70-1923' => 'https://creativecommons.org/licenses/publicdomain/',
            'PD-old-70|Unclear-PD-US-old-70' => 'https://creativecommons.org/licenses/publicdomain/',
            'PD-US' => 'https://creativecommons.org/licenses/publicdomain/',
            'PD-US-no notice' => 'https://creativecommons.org/licenses/publicdomain/',
            'PD-USGov' => 'https://creativecommons.org/licenses/publicdomain/',
            'PD-USGov-NASA' => 'https://www.jsc.nasa.gov/policies.html#Guidelines',
            'PD-USGov-Military-Navy' => 'https://creativecommons.org/licenses/publicdomain/',
            'PD-ineligible' => 'https://creativecommons.org/licenses/publicdomain/'
        ]);
    }

    /**
     * Create a new S3 item by uploading a file and metadata.
     *
     * @param string $accessKey The S3 credential's access key
     * @param string $secretKey The S3 credential's secret key
     * @param string $path The full file path and name to upload
     * @param array $data A file record
     * @return bool|int True if successful; HTTP status code on error, if available, otherwise false
     */
    public function createItem($accessKey, $secret, $path, $data)
    {
        $url = $this->makeURL($path, $data['id']);
        $mediaType = $this->getMediaType($path, $data['mime_type']);
        $collection = $this->getCollection($mediaType);
        $language = $this->getMARC21($data['language']);
        $licenseURL = $this->getLicenseURL($data['license']);
        try {
            $this->put($url, [
                'headers' => [
                    'authorization' => "LOW $accessKey:$secret",
                    'x-amz-auto-make-bucket' => '1',
                    'x-archive-meta01-collection' => $collection,
                    'x-archive-meta-mediatype' => $mediaType,
                    'x-archive-meta-title' => $data['title'],
                    'x-archive-meta-creator' => $data['author'],
                    'x-archive-meta-date' => $data['recording_date'],
                    'x-archive-meta-language' => $language,
                    'x-archive-meta-licenseurl' => $licenseURL,
                    'x-archive-meta-subject' => implode(';', explode("\t", $data['keywords'])),
                    'x-archive-meta-description' => $data['description']
                ],
                'body' => fopen($path, 'rb')
            ]);
            return true;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                log_message('error', "Error archiving $url: " . $response->getStatusCode() .' '. $response->getReasonPhrase());
                return $response->getStatusCode();
            } else {
                log_message('error', "Error archiving $url");
                return false;
            }
        }
    }

    /**
     * Add a file to an existing S3 item.
     *
     * @param string $accessKey The S3 credential's access key
     * @param string $secretKey The S3 credential's secret key
     * @param string $path The full file path and name to upload
     * @param array $data A file record
     * @return bool|int True if successful; HTTP status code on error, if available, otherwise false
     */
    public function addFileToItem($accessKey, $secret, $path, $data)
    {
        $url = $this->makeURL($path, $data['id']);
        try {
            $this->put($url, [
                'headers' => ['authorization' => "LOW $accessKey:$secret"],
                'body' => fopen($path, 'rb')
            ]);
            return true;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                log_message('error', "Error archiving $url: " . $response->getStatusCode() .' '. $response->getReasonPhrase());
                return $response->getStatusCode();
            } else {
                log_message('error', "Error archiving $url");
                return false;
            }
        }
    }

    /**
     * Update the metadata for an existing S3 item.
     *
     * @param string $accessKey The S3 credential's access key
     * @param string $secretKey The S3 credential's secret key
     * @param string $path The full file path and name to upload
     * @param array $data A file record
     * @return bool|int True if successful; HTTP status code on error, if available, otherwise false
     */
    public function updateMetadata($accessKey, $secret, $path, $data)
    {
        $url = self::s3endpoint . self::s3ItemPrefix . $data['id'];
        $mediaType = $this->getMediaType($path, $data['mime_type']);
        $collection = $this->getCollection($mediaType);
        $language = $this->getMARC21($data['language']);
        $licenseURL = $this->getLicenseURL($data['license']);
        try {
            $this->put($url, [
                'headers' => [
                    'authorization' => "LOW $accessKey:$secret",
                    'x-archive-ignore-preexisting-bucket' => 1,
                    'x-archive-meta01-collection' => $collection,
                    'x-archive-meta-mediatype' => $mediaType,
                    'x-archive-meta-title' => $data['title'],
                    'x-archive-meta-creator' => $data['author'],
                    'x-archive-meta-date' => $data['recording_date'],
                    'x-archive-meta-language' => $language,
                    'x-archive-meta-licenseurl' => $licenseURL,
                    'x-archive-meta-subject' => implode(';', explode("\t", $data['keywords'])),
                    'x-archive-meta-description' => $data['description']
                ]
            ]);
            return true;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                log_message('error', "Error updating $url: " . $response->getStatusCode() .' '. $response->getReasonPhrase());
                return $response->getStatusCode();
            } else {
                log_message('error', "Error updating $url");
                return false;
            }
        }
    }

    /**
     * Retrieve a file from a S3 item.
     *
     * This assumes the file in the S3 item and the file to be saved to local storage
     * use the same base name.
     *
     * @param int $file_id The file record ID
     * @param string $path The full path and file name of the file to save to local storage
     * @return bool|int True if successful; HTTP status code on error, if available, otherwise false
     */
    public function download($file_id, $path)
    {
        $base = basename($path);
        $url = self::downloadEndpoint . self::s3ItemPrefix . "$file_id/$base";
        try {
            $response = $this->get($url, ['save_to' => $path]);
            clearstatcache(true, $path);
            return true;
        } catch (RequestException $e) {
            // Truncate file if exception occurs.
            fclose(fopen($path, 'w'));
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                log_message('error', "Error downloading $url: " . $response->getStatusCode() .' '. $response->getReasonPhrase());
                return $response->getStatusCode();
            } else {
                log_message('error', "Error downloading $url");
                return false;
            }
        }
    }

    /**
     * Get the URL to display an Internet Archive item.
     *
     * @param array $file A file record
     * @return string|bool The URL or False if there was an error
     */
    public function getItemURL($file)
    {
        $filename = config_item('upload_path') . $file['source_path'];
        if (is_file($filename) && !filesize($filename)) {
            return 'https://archive.org/details/' . self::s3ItemPrefix . $file['id'];
        } else {
            return false;
        }
    }

    /**
     * Force a file in a S3 item to be downloaded.
     *
     * This prevents a browser from trying to open it.
     *
     * @param int $file_id A file record ID
     * @param string $filename The file in the item to download - only base name is used
     */
    public function forceDownload($file_id, $filename)
    {
        $base = rawurlencode(basename($filename));
        $url = self::downloadEndpoint . self::s3ItemPrefix . "$file_id/$base";

        try {
            $response = $this->head($url, ['debug' => false]);
        } catch (RequestException $e) {
            // Handle 404 responses
            http_response_code(404);
            exit;
        }

        // Set the appropriate status code for the response (e.g., 200, 304)
        $statusCode = $response->getStatusCode();
        http_response_code($statusCode);

        // Let's carry some headers from the Amazon S3 object over to the web server
        foreach ($response->getHeaders() as $name => $values) {
            $value = implode(", ", $values);
            header("{$name}: {$value}");
        }

        // Headers to force browser to download instead of open and play.
        header('Content-Disposition: attachment; filename="'.basename($filename).'"');
        header('Expires: 0');
        header('Content-Transfer-Encoding: binary');
        header('Pragma: no-cache');

        // Internet Explorer-specific headers
        if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }

        // Stop output buffering
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();

        // Only send the body if the file was not modified
        if ($statusCode == 200) {
            readfile($response->getEffectiveUrl());
        }
        exit;
    }
}

