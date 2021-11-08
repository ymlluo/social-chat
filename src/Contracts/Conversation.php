<?php


namespace App\Services\Chat\Contracts;


interface Conversation
{

    public function instance();

    /**
     * get conversation id
     *
     * @return int
     */
    public function id();

    /**
     * get conversation platform
     *
     * @return string
     */
    public function platform();

    /**
     * get conversation user id
     *
     * @return string|int
     */
    public function user_id();

    /**
     * get conversation user
     * @return User
     */
    public function user(): User;

    /**
     * get conversation customer id
     *
     * @return mixed
     */
    public function customerId();

    /**
     * get conversation customer info
     *
     * @return User
     */
    public function customer(): User;

    /**
     * get conversation Assigned UID
     *
     * @return mixed
     */
    public function agentId();


    public function agent();


}