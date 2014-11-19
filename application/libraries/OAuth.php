<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2014 CDC Leuphana University Lueneburg
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

require APPPATH.'libraries/oauth/OAuth.php';
require APPPATH.'libraries/oauth/MWOAuthSignatureMethod.php';

function wfDebugLog($method, $msg)
{
    log_message('debug', "[$method] $msg");
}

class OAuth
{
    private $baseurl;
    private $consumerToken;
    private $privateKey;
    private $consumer;
    private $rsaMethod;

    function __construct($config)
    {
        $this->baseurl = $config['oauth_base_url'];
        $this->consumerToken = $config['oauth_consumer_token'];
        $this->privateKey = file_get_contents($config['oauth_private_key']);
        $this->consumer = new OAuthConsumer($this->consumerToken, $this->privateKey);
        $this->rsaMethod = new MWOAuthSignatureMethod_RSA_SHA1(new OAuthDataStore(), $this->privateKey);
    }

    function initiate()
    {
        $endpoint = $this->baseurl . '/initiate?format=json&oauth_callback=oob';
        $parsed = parse_url($endpoint);
        $params = array();
        parse_str($parsed['query'], $params);
        $params['title'] = 'Special:OAuth/initiate';
        $result = $this->oauthRequest($endpoint, $params);
        return json_decode($result);
    }

    function redirect($token)
    {
        return $this->baseurl . '/authorize?oauth_token=' . $token . '&oauth_consumer_key=' . $this->consumerToken;
    }

    function token($requestToken, $secret, $verifyCode)
    {
        $token = new OAuthToken($requestToken, $secret);
        $endpoint = $this->baseurl . '/token?format=json';
        $parsed = parse_url($endpoint);
        parse_str($parsed['query'], $params);
        $params['oauth_verifier'] = $verifyCode;
        $params['title'] = 'Special:OAuth/token';
        $result = $this->oauthRequest($endpoint, $params, $token);
        return json_decode($result);
    }

    function identify($accessToken, $secret, $issuer = null)
    {
        $token = new OAuthToken($accessToken, $secret);
        $endpoint = $this->baseurl . '/identify';
        $params = ['title' => 'Special:OAuth/identify'];

        // We need to generate our nonce to validate the JSON Web Token.
        $params['oauth_nonce'] = md5(microtime() . mt_rand());

        $data = $this->oauthRequest($endpoint, $params, $token);
        log_message('debug', 'OAuth/identify response: ' . $data);
        $identity = $this->decodeJWT($data);
        // Validate the JWT
        if (!$this->validateJWT($identity, $this->consumerToken, $issuer, $params['oauth_nonce'])) {
            return null;
        } else {
            return $identity;
        }
    }

    private function oauthRequest($url, $params, $token = null)
    {
        $request = OAuthRequest::from_consumer_and_token(
            $this->consumer, // OAuthConsumer for your app
            $token,          // User token, NULL for calls to initiate
            'GET',           // http method
            $url,            // endpoint url (this is signed)
            $params          // extra parameters we want to sign (must include title)
        );
        $request->sign_request($this->rsaMethod, $this->consumer, $token);
        log_message('debug', "OAuth request: $request");
        return $this->httpRequest($url, $request->to_header());
    }

    private function httpRequest($url, $header)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if ($header)
            curl_setopt($curl, CURLOPT_HTTPHEADER, array($header));
        $result = curl_exec($curl);
        if (!$result)
            throw new Exception('cURL Error: ' . curl_error($curl));
        return $result;
    }

    private function decodeJWT($JWT, $key = null) {
        list( $headb64, $bodyb64, $sigb64 ) = explode( '.', $JWT );
        $payload = json_decode($this->urlsafeB64Decode($bodyb64));
        if ($key) {
//             $header = json_decode($this->urlsafeB64Decode($headb64));
//             $sig = $this->urlsafeB64Decode($sigb64);
//             $expectSig = hash_hmac('sha256', "$headb64.$bodyb64", $secret, true);
//             if ($header->alg !== 'HS256' || !$this->compareHash($sig, $expectSig)) {
//                 throw new Exception("Invalid JWT signature from /identify.");
//             }
//             $success = openssl_verify("$headb64.$bodyb64", $sig, $key, 'SHA256');
        }
        return $payload;
    }

    protected function validateJWT($identity, $consumerKey, $issuer = null, $nonce = null) {
        // Verify the issuer is who we expect (server sends $wgCanonicalServer)
        if ($issuer && $identity->iss !== $issuer) {
            print "Invalid Issuer";
            return false;
        }
        // Verify we are the intended audience
        if ($identity->aud !== $consumerKey) {
            print "Invalid Audience";
            return false;
        }
        // Verify we are within the time limits of the token. Issued at (iat) should be
        // in the past, Expiration (exp) should be in the future.
        $now = time();
        if ($identity->iat > $now || $identity->exp < $now) {
            print "Invalid Time";
            return false;
        }
        // Verify we haven't seen this nonce before, which would indicate a replay attack
        if ($nonce && $identity->nonce !== $nonce) {
            print "Invalid Nonce";
            return false;
        }
        return true;
    }

    private function urlsafeB64Decode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat( '=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    // Constant time comparison
    private function compareHash($hash1, $hash2) {
        $result = strlen($hash1) ^ strlen($hash2);
        $len = min(strlen($hash1), strlen($hash2)) - 1;
        for ($i = 0; $i < $len; $i++) {
            $result |= ord($hash1{$i}) ^ ord($hash2{$i});
        }
        return $result == 0;
    }
}
