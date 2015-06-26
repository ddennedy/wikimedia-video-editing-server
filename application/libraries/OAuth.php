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

/**
 * A logging callback function used by the included MWOAuthClient library
 *
 * Logs using the CodeIgniter logging system.
 *
 * @access private
 */
function wfDebugLog($method, $msg)
{
    log_message('debug', "[$method] $msg");
}

/** My high-level OAuth class, oriented towards the MediaWiki OAuth provider */
class OAuth
{
    private $baseurl;
    private $consumerToken;
    private $privateKey;
    private $consumer;
    private $rsaMethod;
    private $issuer;
    private $userAgent;
    private $publishEndpoint;

    /** Construct an OAuth class suitable for use with MediaWiki. */
    function __construct($config)
    {
        $this->baseurl = $config['oauth_base_url'];
        $this->consumerToken = $config['oauth_consumer_token'];
        $this->privateKey = file_get_contents($config['oauth_private_key']);
        $this->consumer = new OAuthConsumer($this->consumerToken, $this->privateKey);
        $this->rsaMethod = new MWOAuthSignatureMethod_RSA_SHA1(new OAuthDataStore(), $this->privateKey);
        $this->issuer = $config['oauth_jwt_issuer'];
        $this->userAgent = $config['http_client_user_agent'];
        $this->publishEndpoint = $config['publish_endpoint'];
    }

    /**
     * Initiate OAuth with the provider.
     *
     * @return object Decoded JSON from the HTTP response, containing an
     * authorization token as object->key and secret as object->secret, which
     * will be supplied to OAuth::token() after the callback.
     */
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

    /**
     * Compute the OAuth provider's redirect URL.
     *
     * @param string $token An authorization token.
     * @return string URL
     */
    function redirect($token)
    {
        return $this->baseurl . '/authorize?oauth_token=' . $token . '&oauth_consumer_key=' . $this->consumerToken;
    }

    /**
     * Request the access token from the OAuth provider.
     *
     * @param string $requestToken The request/authorization token returned by initiate()
     * @param string $secret The request/authorization secret returned by initiate()
     * @param string $verifyCode The verification code provided by the callback
     * from the OAuth provider.
     * @return object Decoded JSON from the HTTP response, containing an access
     * token as object->key and access secret as object->secret. These should be
     * supplied with subsequent API calls as credentials.
     */
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

    /**
     * Get information about the user as a JSON Web Token (http://jwt.io).
     *
     * This also validates the JWT response.
     *
     * @param string $accessToken The OAuth access token
     * @param string $secret The OAuth authorization secret
     * @param string $issuer The expected issuer to be in the JWT.
     * @return object Decoded JSON Web Token
     */
    public function identify($accessToken, $secret, $issuer = null)
    {
        $token = new OAuthToken($accessToken, $secret);
        $endpoint = $this->baseurl . '/identify';
        $params = ['title' => 'Special:OAuth/identify'];

        // We need to generate our nonce to validate the JSON Web Token.
        $params['oauth_nonce'] = md5(microtime() . mt_rand());

        $data = $this->oauthRequest($endpoint, $params, $token);
        log_message('debug', 'OAuth/identify response: ' . $data);
        $identity = $this->decodeJWT($data, config_item('oauth_consumer_secret'));
        // Validate the JWT
        if (!$this->validateJWT($identity, $this->consumerToken, $issuer, $params['oauth_nonce'])) {
            log_message('error', 'OAuth/identify invalid JWT: ' . json_encode($identity));
            return null;
        } else {
            return $identity;
        }
    }

    /**
     * Issue an HTTP GET request using OAuth credentials.
     *
     * @param string $accessToken The OAuth access token
     * @param mixed $params Additional data to put into the query string per API
     * @return string HTTP response body
     */
    public function get($accessToken, $params)
    {
        $url = $this->publishEndpoint . '?' . http_build_query($params);
        $secret = '__unused__';
        $token = new OAuthToken($accessToken, $secret);
        $request = OAuthRequest::from_consumer_and_token(
            $this->consumer, // OAuthConsumer for your app
            $token,          // User token, NULL for calls to initiate
            'GET',           // http method
            $url,            // endpoint url (this is signed)
            $params          // extra parameters we want to sign (must include title)
        );
        $request->sign_request($this->rsaMethod, $this->consumer, $token);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($request->to_header()));
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        if (!$response)
            throw new Exception('cURL Error: ' . curl_error($curl));
        return $response;
    }

    /**
     * Issue an HTTP POST request using OAuth credentials.
     *
     * @param string $accessToken The OAuth access token
     * @param mixed $params Additional data to put into the query string per API
     * @param array $data Associative array of POST values
     * @return string HTTP response body
     */
    public function post($accessToken, $params, $data)
    {
        $url = $this->publishEndpoint . '?' . http_build_query($params);
        $secret = '__unused__';
        $token = new OAuthToken($accessToken, $secret);
        $request = OAuthRequest::from_consumer_and_token(
            $this->consumer, // OAuthConsumer for your app
            $token,          // User token, NULL for calls to initiate
            'POST',           // http method
            $url,            // endpoint url (this is signed)
            $params          // extra parameters we want to sign (must include title)
        );
        $request->sign_request($this->rsaMethod, $this->consumer, $token);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($request->to_header()));
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        if (!$response)
            throw new Exception('cURL Error: ' . curl_error($curl));
        return $response;
    }

    /**
     * Issue an HTTP GET request during the OAuth authorization process.
     *
     * @access private
     * @param string $url The URL
     * @param mixed $data Additional data to sign as associative array or object.
     * @param string $token An authorization or access token
     * @return string HTTP response body
     */
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

    /**
     * Issue a simple HTTP GET request.
     *
     * @access private
     * @param string $url The full URL
     * @param array $header Additional HTTP headers as an associative array
     * @return string HTTP response body
     */
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

    /**
     * Decode a JSON Web Token (http://jwt.io).
     *
     * Does not validate it.
     *
     * @access private
     * @param string $JWT The JSON Web Token
     * @param string $secret The optional OAuth consumer secret used to
     * verify the signature, skipped if not supplied
     * @return object Decoded JSON payload of the JWT
     */
    private function decodeJWT($JWT, $secret = null) {
        list( $headb64, $bodyb64, $sigb64 ) = explode( '.', $JWT );
        $payload = json_decode($this->urlsafeB64Decode($bodyb64));
        if ($secret) {
            $header = json_decode($this->urlsafeB64Decode($headb64));
            $sig = $this->urlsafeB64Decode($sigb64);
            $expectSig = hash_hmac('sha256', "$headb64.$bodyb64", $secret, true);
            if ($header->alg !== 'HS256' || !$this->compareHash($sig, $expectSig)) {
                throw new Exception("Invalid JWT signature from /identify.");
            }
        }
        return $payload;
    }

    /**
     * Validate the JWT against issuer, OAuth client/consumer key/token, and nonce.
     *
     * @param object $identity The decoded JWT from the identify API
     * @param string $consumerKey The client/consumer token/key
     * @param string $isser Optional issuer against which to validate
     * @param string $none Optional nonce value, usually as a MD5 hash
     * @return bool False if error
     */
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

    /**
     * Decode a URL-encoded base64 string.
     *
     * @access private
     * @param string $input URL-encoded base64 string
     * @return string decoded value, possibly binary
     */
    private function urlsafeB64Decode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat( '=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Compare two short binary hash values to see if they are the same.
     *
     * @access private
     * @param string $hash1 The first hash value
     * @param string $hash2 The second hash value
     * @return bool True if they are the same
     */
    private function compareHash($hash1, $hash2) {
        // Constant time comparison
        $result = strlen($hash1) ^ strlen($hash2);
        $len = min(strlen($hash1), strlen($hash2)) - 1;
        for ($i = 0; $i < $len; $i++) {
            $result |= ord($hash1{$i}) ^ ord($hash2{$i});
        }
        return $result == 0;
    }
}
