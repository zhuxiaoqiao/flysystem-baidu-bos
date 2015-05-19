<?php

namespace Zhuxiaoqiao\Baidu\Bos;

use BaiduBce\Exception\BceBaseException;
use BaiduBce\Log\LogFactory;
use BaiduBce\Services\Bos\BosClient;
use BaiduBce\Services\Bos\BosOptions;
use BaiduBce\Util\MimeTypes;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;

class BaiduBosAdapter extends AbstractAdapter
{
    /**
     * @var array
     */
    protected static $resultMap = [
        BosOptions::CONTENT_LENGTH => 'size',
        BosOptions::CONTENT_TYPE => 'mimetype',
        BosOptions::ETAG => 'etag',
        BosOptions::CONTENT_MD5 => 'contentMd5',
        BosOptions::DATE => 'date',
        BosOptions::LAST_MODIFIED => 'lastModified',
        BosOptions::USER_METADATA => 'userMetadata',
    ];

    /**
     * @var array
     */
    protected static $metaOptions = [
        BosOptions::CONTENT_LENGTH,
        BosOptions::CONTENT_TYPE,
        BosOptions::CONTENT_MD5,
        BosOptions::CONTENT_SHA256,
        BosOptions::USER_METADATA,
    ];

    protected $pathSeparator = DIRECTORY_SEPARATOR;

    protected $bucket;
    protected $client;
    private $logger;

    public function __construct(BosClient $client, $bucket)
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->logger = LogFactory::getLogger('BaiduBos');
    }

    public function getBucket()
    {
        return $this->bucket;
    }

    public function getClient()
    {
        return $this->client;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        if (!$this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        try {
            $this->client->copyObject($this->bucket, $path, $this->bucket, $newpath);
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        try {
            $this->client->deleteObject($this->bucket, $path);
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $objects = $this->listContents($dirname, true);
        try {
            foreach ($objects as $object) {
                $this->client->deleteObject($this->bucket, $object['path']);
            }
            $dirname = $dirname . $this->pathSeparator;
            $this->client->deleteObject($this->bucket, $dirname);
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        $dirname = rtrim($dirname, '/') . '/';
//        $dirname = $dirname . $this->pathSeparator;
        try {
            $result = $this->client->putObjectFromString($this->bucket, $dirname, '');
            if (!$result) {
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        return compact('visibility');
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        try {
            $this->client->getObjectMetadata($this->bucket, $path);
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        try {
            $contents = $this->client->getObjectAsString($this->bucket, $path);
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }
        return compact('contents', 'path');
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        try {
            $outputStream = fopen('php://memory', 'r+');
            $response = $this->client->getObject($this->bucket, $path, $outputStream);
            rewind($outputStream);
            unset($response);
            $response['stream'] = $outputStream;
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }
        return $response;
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $result = [];
        $prefix = empty($directory) ? $directory : ($directory . '/');
        $delimiter = $recursive ? '' : '/';
        $marker = '';
        $options = array(
//            BosOptions::MAX_KEYS => 1000,
            BosOptions::PREFIX => $prefix,
//            BosOptions::MARKER => $marker,
            BosOptions::DELIMITER => $delimiter,
        );

        try {
            while (true) {
                if ($marker !== null) {
                    $options[BosOptions::MARKER] = $marker;
                }
                $response = $this->client->listObjects($this->bucket, $options);
                foreach ($response->contents as $object) {
                    if ($object->key != $prefix)
                        $result[] = [
                            'timestamp' => $object->lastModified,
                            'type' => 'file',
                            'path' => $object->key,
                            'size' => $object->size,
                        ];
                }
                if (isset($response->commonPrefixes)) {
                    foreach ($response->commonPrefixes as $object) {
                        $result[] = [
                            'type' => 'dir',
                            'path' => $object->prefix,
                        ];
                    }
                }
                if ($response->isTruncated) {
                    $marker = $response->nextMarker;
                } else {
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return [];
        }
        return $result;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        try {
            $result = $this->client->getObjectMetadata($this->bucket, $path);
        } catch (\Exception $e) {
            $this->logger->debug(gettype($e) . ": " . $e->getMessage());
            return false;
        }
        return $this->normalizeResponse($result, $path);
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        $visibility = AdapterInterface::VISIBILITY_PUBLIC;
        return compact('visibility');
//        return false;
    }

    /**
     * Upload an object.
     *
     * @param        $path
     * @param        $body
     * @param Config $config
     *
     * @return array
     */
    protected function upload($path, $body, Config $config)
    {
        $options = $this->getOptionsFromConfig($config);
        $response = null;

        try {
            if (is_string($body)) {
                $response = $this->client->putObjectFromString($this->bucket, $path, $body, $options);
            } else {
                if (isset($options['file'])) {
                    $file = $options['file'];
                    $response = $this->client->putObjectFromFile($this->bucket, $path, $file, $options);
                } else {
                    if (!isset($options[BosOptions::CONTENT_TYPE])) {
                    }
                    $contentLength = $options[BosOptions::CONTENT_LENGTH];
                    unset($options[BosOptions::CONTENT_LENGTH]);
                    $contentMd5 = $options[BosOptions::CONTENT_MD5];
                    unset($options[BosOptions::CONTENT_MD5]);
                    $response = $this->client->putObject($this->bucket, $path, $body, $contentLength, $contentMd5, $options);
                    if (is_resource($body)) {
                        fclose($body);
                    }
                }
            }
        } catch (BceBaseException $e) {
            if (stcmp(gettype($e), "BceClientException") == 0) {
                $this->logger->debug("BceClientException: " . $e->getMessage());
            }
            if (stcmp(gettype($e), "BceServerException") == 0) {
                $this->logger->debug("BceServerException: " . $e->getMessage());
            }
            if (is_resource($body)) {
                fclose($body);
            }
            return false;
        } catch (\InvalidArgumentException $e) {
            $this->logger->debug("InvalidArgumentException: " . $e->getMessage());
            if (is_resource($body)) {
                fclose($body);
            }
            return false;
        } catch (\Exception $e) {
            $this->logger->debug("Exception: " . $e->getMessage());
            if (is_resource($body)) {
                fclose($body);
            }
            return false;
        }
        return $this->normalizeResponse($response->metadata, $path);
    }

    /**
     * Get options from the config.
     *
     * @param Config $config
     *
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = [];

        if ($mimetype = $config->get('mimetype')) {
            // For local reference
            $options['mimetype'] = $mimetype;
            // For external reference
            $options[BosOptions::CONTENT_TYPE] = $mimetype;
        }

        foreach (static::$metaOptions as $option) {
            if (!$config->has($option)) {
                continue;
            }
            $options[$option] = $config->get($option);
        }

        return $options;
    }

    /**
     * Normalize the object result array.
     *
     * @param array $response
     *
     * @return array
     */
    protected function normalizeResponse(array $response, $path = null)
    {
        $result = ['path' => $path ?: $this->removePathPrefix(null)];

        if (isset($response['date'])) {
            $result['timestamp'] = $response['date']->getTimestamp();
        }

        if (isset($response['lastModified'])) {
            $result['timestamp'] = $response['lastModified']->getTimestamp();
        }

        if (substr($result['path'], -1) === '/') {
            $result['type'] = 'dir';
            $result['path'] = rtrim($result['path'], '/');

            return $result;
        }

        return array_merge($result, Util::map($response, static::$resultMap), ['type' => 'file']);
    }
}
