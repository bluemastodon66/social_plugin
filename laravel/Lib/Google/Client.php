<?php

namespace App\Lib\Google;

use Guzzle\Http\Client as HttpClient;
use Guzzle\Http\Exception\BadResponseException;
use Illuminate\Support\Facades\Request;

class Client {

    protected $_id_, $_secret_, $_callback_;
    protected $baseUrl = 'https://accounts.google.com/o/oauth2/auth';
    protected $scopes = ['https://www.googleapis.com/auth/userinfo.profile', 'https://www.googleapis.com/auth/userinfo.email'];
    protected $oauthUrl = 'https://accounts.google.com/o/oauth2/token';
    protected $client;

    /**
     * 
     * @param type $client_key
     * @param type $secret
     * @param type $redirect_uri
     */
    public function __construct($client_key, $secret, $redirect_uri) {
        $this->_id_ = $client_key;
        $this->_secret_ = $secret;
        $this->_callback_ = $redirect_uri;
        $this->baseUrl .= '?client_id='
                . $client_key . '&redirect_uri=' . urlencode($redirect_uri) . "&response_type=code";
        $this->client = new HttpClient();
    }

    public function getOauthUrl() {

        $urls = urlencode(implode(" ", $this->scopes));
        $url = $this->baseUrl . "&scope=" . $urls;
        return $url;
    }

    /**
     *
     * @return type object ['id','type','name','email','picture',url']
     * 
     */
    public function getData() {
        $params = Request::only([
                    'code'
        ]);
        if (empty($params['code'])) {
            return '';
        }
        $postData = [
            "code" => $params['code'],
            "client_id" => $this->_id_,
            "client_secret" => $this->_secret_,
            "redirect_uri" => $this->_callback_,
            "grant_type" => "authorization_code"
        ];

        try {
            $request = $this->client->post($this->oauthUrl, [], $postData);
            $response = $request->send();
        } catch (BadResponseException $ex) {
            return null;
        }

        $jsonObj = @json_decode($response->getBody(), true);
        if (empty($jsonObj) || empty($jsonObj['access_token'])) {
            return '';
        }
        $accessToken = $jsonObj['access_token'];

        try {
            $request = $this->client->get('https://www.googleapis.com/plus/v1/people/me', null, array('exceptions' => false));
            $request->setHeader('Authorization', sprintf('Bearer %s', $accessToken));
            $response = $request->send();
        } catch (BadResponseException $ex) {
            return null;
        }

        $jsonObj = @json_decode($response->getBody(), true);
        if (empty($jsonObj)) {
            return null;
        }
        if(empty( $jsonObj['displayName'])) {
             $jsonObj['displayName'] = 'no name';
        }
        if(empty( $jsonObj['url'])) {
             $jsonObj['url'] = '';
        }

        $ret = [
            'id' => 'google_' . $jsonObj['id'],
            'type' => 'google',
            'name' => $jsonObj['displayName'],
            'email' => '',
            'picture' => '',
            'url' => $jsonObj['url']
        ];

        if (!empty($jsonObj['image']) && !empty($jsonObj['image']['url'])) {
            $ret['picture'] = $jsonObj['image']['url'];
            $ret['picture'] = str_replace('sz=50', 'sz=320', $ret['picture']);
        }
        if (!empty($jsonObj['emails']) && count($jsonObj['emails']) > 0) {
            $ret['email'] = strtolower($jsonObj['emails'][0]['value']);
        }

        return $ret;
    }

}
