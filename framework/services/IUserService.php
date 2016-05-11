<?php

namespace Framework\Services;

use Framework\Di\Bean;

/**
 * Interface IUserService is used to let the api user create its own
 * implementation of a user service, the userservice must perform a
 * login and create the user object
 */
interface IUserService extends Bean
{
    /**
     * @param $user
     * @param $pw
     * @return mixed null if the login failed, the user object otherwise
     */
    public function login($user, $pw);

    /**
     * Must return all roles as string array
     * @param $user
     * @return mixed
     */
    public function roles($user);
}