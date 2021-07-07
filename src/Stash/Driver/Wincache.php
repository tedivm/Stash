<?php

/*
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Driver;

use Stash;
use Stash\Exception\RuntimeException;
use Stash\Interfaces\DriverInterface;

/**
 * The Wincache driver is a wrapper for the Wincache extension,
 * which allows developers to store data in memory on Windows.
 *
 * @author  Vincent Lark <vincent.lark@gmail.com>
 */
class Wincache implements DriverInterface
{
    /**
     * Default maximum time an Item will be stored.
     *
     * @var int
     */
    protected $ttl = 300;

    /**
     * This is an install specific namespace used to segment different applications
     * from interacting with each other when using Wincache.
     * It's generated by creating an md5 of this file's location.
     *
     * @var string
     */
    protected $wincacheNamespace;

    /**
     * Initializes the driver.
     *
     * @throws RuntimeException 'Extension is not installed.'
     */
    public function __construct()
    {
        if (!static::isAvailable()) {
            throw new RuntimeException('Extension is not installed.');
        }
    }

    /**
     * This function should takes an array which is used to pass option values to the driver.
     *
     * * ttl - This is the maximum time the item will be stored.
     * * namespace - This should be used when multiple projects may use the same library.
     *
     * @param array $options
     *
     * @throws \Stash\Exception\RuntimeException
     */
    public function setOptions(array $options = array())
    {
        if (isset($options['ttl']) && is_numeric($options['ttl'])) {
            $this->ttl = (int) $options['ttl'];
        }

        $this->wincacheNamespace = isset($options['namespace']) ? $options['namespace'] : md5(__FILE__);
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getData($key)
    {
        $keyString = self::makeKey($key);
        $success = null;
        $data = wincache_ucache_get($keyString, $success);

        return $success ? $data : false;
    }

    /**
     * {@inheritdoc}
     */
    public function storeData($key, $data, $expiration)
    {
        $life = max(0, $this->getCacheTime($expiration));

        return wincache_ucache_set($this->makeKey($key), array('data' => $data, 'expiration' => $expiration), $life);
    }

    /**
     * {@inheritdoc}
     */
    public function clear($key = null)
    {
        if (!isset($key)) {
            return wincache_ucache_clear();
        } else {
            $keyString = $this->makeKey($key);
            $keyLength = strlen($keyString);

            $info = wincache_ucache_info();

            foreach ($info['ucache_entries'] as $entry) {
                if (strlen($entry['key_name']) >= $keyLength
                    && $keyString == substr($entry['key_name'], 0, $keyLength)) {
                    wincache_ucache_delete($entry['key_name']);
                }
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function purge()
    {
        // WinCache auto purges

        return true;
    }

    /**
     * This driver is available if the wincache extension is present and loaded on the system.
     *
     * @return bool
     */
    public static function isAvailable()
    {
        return (extension_loaded('wincache')) && ((php_sapi_name() !== 'cli') || ini_get('wincache.enablecli'));
    }

    /**
     * Turns a key array into a string.
     *
     * @param array $key
     *
     * @return string
     */
    protected function makeKey($key)
    {
        $keyString = $this->wincacheNamespace.'::';

        foreach ($key as $piece) {
            $keyString .= $piece.'::';
        }

        return $keyString;
    }

    /**
     * Converts a timestamp into a TTL.
     *
     * @param int $expiration
     *
     * @return int
     */
    protected function getCacheTime($expiration)
    {
        $life = $expiration - time();

        return $this->ttl < $life ? $this->ttl : $life;
    }

    /**
     * {@inheritdoc}
     */
    public function isPersistent()
    {
        return true;
    }
}
