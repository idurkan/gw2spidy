<?php

namespace GW2Spidy\WorkerQueue;

use GW2Spidy\Util\Functions;

use GW2Spidy\Queue\WorkerQueueManager;
use GW2Spidy\Queue\WorkerQueueItem;

use GW2Spidy\DB\ItemSubType;
use GW2Spidy\DB\ItemSubTypeQuery;
use GW2Spidy\DB\ItemType;
use GW2Spidy\DB\ItemTypeQuery;
use GW2Spidy\TradingPostSpider;

class ItemTypeDBWorker implements Worker {
    public function getRetries() {
        return 1;
    }

    public function work(WorkerQueueItem $item) {
        $market = TradingPostSpider::getInstance();

        $marketData = $market->getMarketData();

        foreach ((array)$marketData['types'] as $mainTypeData) {
            if (!isset($mainTypeData['id']) || !isset($mainTypeData['name'])) {
                continue;
            }

            $type = ItemTypeQuery::create()->findPK($mainTypeData['id']);

            if ($type) {
                if (($p = Functions::almostEqualCompare($mainTypeData['name'], $type->getTitle())) > 50) {
                    $type->setTitle($mainTypeData['name']);
                    $type->save();

                } else {
                    throw new \Exception("Title for ID no longer matches! maintype [{$p}] [json::{$mainTypeData['id']}::{$mainTypeData['name']}] vs [db::{$type->getId()}::{$type->getTitle()}]");
                }
            } else {
                $type = new ItemType();
                $type->setId($mainTypeData['id']);
                $type->setTitle($mainTypeData['name']);
                $type->save();
            }

            foreach ((array)$mainTypeData['subs'] as $subTypeData) {
                if (!isset($subTypeData['id']) || !isset($subTypeData['name'])) {
                    continue;
                }

                $subtype = ItemSubTypeQuery::create()->findPK(array($subTypeData['id'], $type->getId()));

                if ($subtype) {
                    if (($p = Functions::almostEqualCompare($mainTypeData['name'], $type->getTitle())) > 50) {
                        if (!$subtype->getMainType()->equals($type)) {
                            throw new \Exception("Maintype no longer matches! [{$subTypeData['name']}] [{$subTypeData['id']}]");
                        }

                        $subtype->setTitle($subTypeData['name']);
                        $subtype->save();

                    } else {
                        throw new \Exception("Title for ID no longer matches! subtype [{$p}] [json::{$subTypeData['id']}::{$subTypeData['name']}] vs [db::{$subtype->getId()}::{$subtype->getTitle()}]");
                    }
                } else {
                    $subtype = new ItemSubType();
                    $subtype->setMainType($type);
                    $subtype->setId($subTypeData['id']);
                    $subtype->setTitle($subTypeData['name']);
                    $subtype->save();
                }
            }
        }
    }

    public static function enqueueWorker() {
        $queueItem = new WorkerQueueItem();
        $queueItem->setWorker("\\GW2Spidy\\WorkerQueue\\ItemTypeDBWorker");
        // $queueItem->setPriority(WorkerQueueItem::PRIORITY_TYPEDB);

        WorkerQueueManager::getInstance()->enqueue($queueItem);

        return $queueItem;
    }
}

?>