<?php


namespace App\Services\Chat\Contracts;


interface Oauth
{
    public function redirect($scope=[]);

    public function callback();

    public function authorize();

    public function userinfo( $refresh = false);

    public function accessToken();

    public function refreshAccessToken();


}