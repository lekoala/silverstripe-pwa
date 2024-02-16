<?php

namespace LeKoala\SsPwa;

interface ServiceWorkerCacheProvider
{
    /**
     * @return array<string>
     */
    public static function getServiceWorkerCachedPaths(): array;
}
