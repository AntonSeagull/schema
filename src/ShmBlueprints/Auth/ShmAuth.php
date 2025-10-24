<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;

/**
 * Main authentication factory class
 * 
 * This class provides factory methods for creating different types of authentication
 * handlers including SMS, email, social, Apple, and other authentication methods.
 */
class ShmAuth
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Constructor implementation
    }

    /**
     * Create SMS authentication handler
     * 
     * @return ShmSmsAuth SMS authentication instance
     */
    public function sms(): ShmSmsAuth
    {
        return new ShmSmsAuth();
    }

    /**
     * Create message authentication handler
     * 
     * @return ShmMsgAuth Message authentication instance
     */
    public function msg(): ShmMsgAuth
    {
        return new ShmMsgAuth();
    }

    /**
     * Create email authentication handler
     * 
     * @return ShmEmailAuth Email authentication instance
     */
    public function email(): ShmEmailAuth
    {
        return new ShmEmailAuth();
    }

    /**
     * Create login authentication handler
     * 
     * @return ShmLoginAuth Login authentication instance
     */
    public function login(): ShmLoginAuth
    {
        return new ShmLoginAuth();
    }

    /**
     * Create social authentication handler
     * 
     * @return ShmSocAuth Social authentication instance
     */
    public function soc(): ShmSocAuth
    {
        return new ShmSocAuth();
    }

    /**
     * Create Apple authentication handler
     * 
     * @return ShmAppleAuth Apple authentication instance
     */
    public function apple(): ShmAppleAuth
    {
        return new ShmAppleAuth();
    }

    /**
     * Create passport authentication handler
     * 
     * @return ShmPassportAuth Passport authentication instance
     */
    public function passport(): ShmPassportAuth
    {
        return new ShmPassportAuth();
    }

    /**
     * Create call authentication handler
     * 
     * @return ShmCallAuth Call authentication instance
     */
    public function call(): ShmCallAuth
    {
        return new ShmCallAuth();
    }
}
