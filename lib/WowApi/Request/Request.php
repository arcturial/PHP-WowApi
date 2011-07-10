<?php
namespace WowApi\Request;

use WowApi\Client;
use WowApi\Exception\ApiException;
use WowApi\Exception\RequestException;
use WowApi\Cache\CacheInterface;
use WowApi\Utilities;

abstract class Request implements RequestInterface
{
    protected $headers = array(
        'Expect' => '',
        'Accept' => 'application/json',
        'Accept-Encoding' => 'gzip',
        'Content-Type' => 'application/json',
        'User-Agent' => 'PHP WowApi (http://github.com/dancannon/PHP-WowApi)',
    );

    /**
     * @var null|\WowApi\Client
     */
    protected $client = null;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function get($path, array $parameters = array(), array $options = array())
    {
        return $this->send($path, $parameters, 'GET', $options);
    }

    public function post($path, array $parameters = array(), array $options = array())
    {
        return $this->send($path, $parameters, 'POST', $options);
    }

    public function put($path, array $parameters = array(), array $options = array())
    {
        return $this->send($path, $parameters, 'PUT', $options);
    }

    public function delete($path, array $parameters = array(), array $options = array())
    {
        return $this->send($path, $parameters, 'DELETE', $options);
    }

    public function send($path, array $parameters = array(), $httpMethod = 'GET', array $options = array())
    {
        $options = array_merge($this->getOptions(), $options);

        // Attempt to set If-Modified-Since header
        if($this->client->getCache() !== null) {
            $cache = $this->client->getCache()->getCachedResponse($path, $parameters);
            if (isset($cache) && isset($cache['last-modified'])) {
                $this->setHeader('If-Modified-Since', gmdate("D, d M Y H:i:s", $cache['last-modified']) . " GMT");
            }
        }

        // Attempt to authenticate application
        if($this->getOption('publicKey') !== null && $this->getOption('privateKey') !== null) {
            $stringToSign =  "$httpMethod\n" . $this->getHttpDate(time()) . "\n$path\n";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, utf8_encode($this->getOption('privateKey'))));

            $this->setHeader("Authorization", "BNET " . $this->getOption('publicKey') . "+$signature");
        }

        // create full url
        $url = strtr($options['url'], array(
            ':protocol' => $options['protocol'],
            ':region' => $options['region'],
            ':path' => trim($path, '/'),
        ));
        
        // Get response
        $response = $this->makeRequest($url, $parameters, $httpMethod, $options);

        //Check for 304 Not Modified header
        if(isset($cache) && $response['headers']['http_code'] === 304) {
            return $cache;
        } else {
            //$response = Utilities::decode(json_decode($response['response']));
            $response = json_decode($response['response']);
            if (strpos($response['headers']['content_type'], 'application/json') !== false) {
                $response = json_decode($response['response'], true);
            } else {
                $response = (array) $response['response'];
            }
            // Check for errors
            if(!is_array($response)) {
                throw new ApiException('The response was not valid');
            } elseif(isset($response['status']) && $response['status'] = 'nok') {
                if(isset($response['reason'])) {
                    throw new ApiException($response['reason']);
                } else {
                    throw new ApiException("Unknown error");
                }
            }
        }
        if($this->client->getCache() !== null) {
            $this->client->getCache()->setCachedResponse($path, $parameters, $response, time());
        }
        return $response;
    }

    /**
     * Create an RFC 1123 HTTP-Date from various date values
     *
     * @param string|int $date Date to convert
     *
     * @return string
     */
    public function getHttpDate($date)
    {
        if (!is_numeric($date)) {
            $date = strtotime($date);
        }

        return gmdate('D, d M Y H:i:s', $date) . ' GMT';
    }

    public function getRawHeaders()
    {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        return $headers;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getHeader($name) {
        return $this->headers[$name];
    }

    public function setHeaders($headers) {
        $this->headers = $headers;
    }

    public function setHeader($name, $value) {
        $this->headers[$name] = $value;
    }

    public function getOption() {
        return $this->getOptions();
    }

    public function setOption($name, $value) {
        $this->setOption($name, $value);
    }

    public function getOptions() {
        return $this->getOptions();
    }

    public function setOptions($options) {
        return $this->client->setOptions($options);
    }
}
