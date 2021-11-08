<?php


namespace App\Services\Chat\Contracts;


interface User
{
    /**
     * get user info
     *
     * @return array
     */
    public function info(): array;

    /**
     * get user id
     *
     * @return mixed
     */
    public function id();

    /**
     * get user nickname
     *
     * @return string
     */
    public function nickname(): string;

    /**
     * get user avatar url
     *
     * @return string
     */
    public function avatar(): string;


}