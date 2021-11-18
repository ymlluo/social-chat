<?php


namespace App\Services\Chat\Contracts;


interface Message
{

    public function toUserName();

    public function fromUserName();

    public function createTime();

    public function type();

    public function event();

    public function reply();



}