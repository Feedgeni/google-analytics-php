<?php

namespace Kudobuzz;

use DateTime;
use Google_Service_Analytics;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
//use Illuminate\Contracts\Cache\Repository;


// NOTE: We're removing "cache/filesystem-adapter": "^1.0" from the composer.json for PHP 8.x / Laravel 9.x
// support in API Gateway. Since we don't use any of these classes in the API Gateway, we can safely remove
// but know that if we use them, this will all bomb
class AnalyticsClient
{

    protected $cachePath;

    /** @var \Google_Service_Analytics */
    protected $service;

    /** @var \Cache */
    protected $cache;

    /** @var int */
    protected $cacheLifeTimeInMinutes = 0;

    protected $cacheTime = 3600;// 3600 second maps to an hour of cache time


    public function __construct(Google_Service_Analytics $service)
    {
        $this->service = $service;

        //$dirSeparator = DIRECTORY_SEPARATOR; 

        $filesystemAdapter = new Local(__DIR__.'/');

        $filesystem = new Filesystem( $filesystemAdapter );

        $this->cache = new FilesystemCachePool( $filesystem );

        //$this->cache->setFolder( realpath(__DIR__)."/Cache/analytics-cache" );
        
    }

    /**
     * Set the cache time.
     *
     * @param int $cacheLifeTimeInMinutes
     *
     * @return self
     */
    public function setCacheLifeTimeInMinutes(int $cacheLifeTimeInMinutes)
    {
        $this->cacheLifeTimeInMinutes = $cacheLifeTimeInMinutes;

        return $this;
    }

    /**
     * Query the Google Analytics Service with given parameters.
     *
     * @param string    $viewId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param string    $metrics
     * @param array     $others
     *
     * @return array|null
     */
    public function performQuery(string $viewId, DateTime $startDate, DateTime $endDate, string $metrics, array $others = [])
    {
        $cacheName = $this->determineCacheName(func_get_args());

        if( $this->cache->hasItem( $cacheName ) )
            return $this->cache->getItem( $cacheName )->get();

        $item = $this->cache->getItem( $cacheName );
        
        $item->set( 
            $this->service->data_ga->get(
                "ga:{$viewId}",
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
                $metrics,
                $others
            ) 
        );
        
        $item->expiresAfter( $this->cacheTime );
        
        $this->cache->save( $item );

        return $this->cache->getItem( $cacheName )->get();

    }

    protected function fetchFromCache(&$cachedString){
        return $cachedString->get();
    }

    public function getAnalyticsService()
    {
        return $this->service;
    }

    /*
     * Determine the cache name for the set of query properties given.
     */
    protected function determineCacheName(array $properties): string
    {
        //return 'kudobuzz.google-analytics.'.md5(serialize($properties));
        return 'kudobuzzgoogleanalytics'.md5(serialize($properties));
    }

}
