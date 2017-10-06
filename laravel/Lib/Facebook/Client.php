<?php

namespace App\Lib\Facebook;

use Guzzle\Http\Client as HttpClient;
use Guzzle\Http\Exception\BadResponseException;
use Illuminate\Support\Facades\Request;

class Client {

    protected $id, $secret, $redirect;
    protected $baseURL = 'https://graph.facebook.com/v2.8/oauth/authorize';
    protected $tokenURL = 'https://graph.facebook.com/v2.8/oauth/access_token';
    protected $graphURL = 'https://graph.facebook.com/v2.8/me';
    protected $scopes = ['email'];
    protected $client;

    public function __construct($id, $secret, $redirect) {
        $this->id = $id;
        $this->secret = $secret;
        $this->redirect = $redirect;
        $this->client = new HttpClient();
    }

    public function getOauthUrl() {
        $url = $this->baseURL . "?client_id=" . $this->id . '&type=client_cred'
                . '&redirect_uri=' . urlencode($this->redirect) . '&scope=' . implode(',', $this->scopes);
        return $url;
    }
/**
 *
 * @return type object ['id','type','name','email','picture',url']
 * 
 */
    public function getData() {
        $params = Request::only(['code']);
        if (empty($params['code'])) {
            return null;
        }
        $postData = [
            'client_id' => $this->id,
            'code' => $params['code'],
            'client_secret' => $this->secret,
            'redirect_uri' => $this->redirect
        ];
        try {
            $request = $this->client->post($this->tokenURL, null, $postData);
            $response = $request->send();
        } catch (BadResponseException $ex) {
            return null;
        }

        $jsonObj = @json_decode($response->getBody(), true);
        if (empty($jsonObj) || empty($jsonObj['access_token'])) {
            return null;
        }

        $fields = 'id,name,email,picture.width(320).height(320).as(picture)';
        try {
            $request = $this->client->get($this->graphURL . "?fields=" . $fields, [
                'Authorization' => 'Bearer ' . $jsonObj['access_token']
                    ], null);
            $response = $request->send();
        } catch (BadResponseException $ex) {
            return null;
        }
        $jsonObj = @json_decode($response->getBody(), true);
        if (empty($jsonObj)) {
            return null;
        }
        if(empty( $jsonObj['name'])) {
             $jsonObj['name'] = 'no name';
        }

        $ret = [
            'id' => 'fb_' . $jsonObj['id'],
            'type' => 'fb',
            'name' => $jsonObj['name'],
            'email' => '',
            'picture' => '',
            'url' => 'https://www.facebook.com/?fid=' . $jsonObj['id']
        ];

        if (!empty($jsonObj['picture']) && !empty($jsonObj['picture']['data'])) {
            $ret['picture'] = $jsonObj['picture']['data']['url'];
        }
        if (!empty($jsonObj['email'])) {
            $ret['email'] = strtolower($jsonObj['email']);
        } 
        return $ret;
    }

}
