<?php namespace Zhuxiaoqiao\Flysystem\BaiduBos;

use BaiduBce\Services\Bos\BosOptions;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class PutFilePlugin implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'putFile';
    }

    public function handle($key, $filename, $options = array())
    {
        $adapter = $this->filesystem->getAdapter();
        if ($adapter instanceof BaiduBosAdapter) {
            try {
                $response = $adapter->getClient()->putObjectFromFile($adapter->getBucket(), $key, $filename, $options);
                $etag = $response->metadata[BosOptions::ETAG];
                $timestamp = $response->metadata[BosOptions::DATE]->getTimestamp();
                $mimetype = $response->metadata[BosOptions::CONTENT_TYPE];
                return array_merge(compact('etag', 'timestamp', 'mimetype'), ['path' => $key, 'type' => 'file']);
            } catch (\Exception $e) {
                $adapter->getLogger()->debug(gettype($e) . ": " . $e->getMessage());
                return false;
            }
        }
    }
}