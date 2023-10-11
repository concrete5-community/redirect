<?php

namespace MLRedirect\OSDetector;

use DeviceDetector\Cache\Cache as DDCache;
use Concrete\Core\Cache\Level\ExpensiveCache;

final class Cache implements DDCache
{
    const KEY_NAMESPACE = 'mlredirect_osdetector';

    /**
     * @var \Concrete\Core\Cache\Cache
     */
    private $coreCache;

    public function __construct(ExpensiveCache $coreCache)
    {
        $this->coreCache = $coreCache;
    }

    /**
     * {@inheritdoc}
     *
     * @see \DeviceDetector\Cache\Cache::fetch()
     */
    public function fetch($id)
    {
        $item = $this->coreCache->getItem(self::KEY_NAMESPACE . '/' . $id);

        return $item->isHit() ? $item->get() : false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \DeviceDetector\Cache\Cache::fetch()
     */
    public function contains($id)
    {
        $item = $this->coreCache->getItem(self::KEY_NAMESPACE . '/' . $id);

        return $item->isHit();
    }

    /**
     * {@inheritdoc}
     *
     * @see \DeviceDetector\Cache\Cache::save()
     */
    public function save($id, $data, $lifeTime = 0)
    {
        $item = $this->coreCache->getItem(self::KEY_NAMESPACE . '/' . $id);
        $item->set($data);
        if (func_num_args() > 2) {
            $item->setTTL($lifeTime);
        }
        $this->coreCache->save($item);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \DeviceDetector\Cache\Cache::delete()
     */
    public function delete($id)
    {
        $this->coreCache->delete(self::KEY_NAMESPACE . '/' . $id);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \DeviceDetector\Cache\Cache::flushAll()
     */
    public function flushAll()
    {
        $this->coreCache->delete(self::KEY_NAMESPACE);
    }
}
