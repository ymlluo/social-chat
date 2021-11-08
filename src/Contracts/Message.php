<?php


namespace App\Services\Chat\Contracts;


interface Message
{

    public function toUserName();

    public function fromUserName();

    public function createTime();

    public function msgType();

    public function event();

    public function getData();

    public function reply();

    public function send();


}