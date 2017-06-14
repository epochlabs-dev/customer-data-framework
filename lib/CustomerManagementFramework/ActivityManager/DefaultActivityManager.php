<?php
/**
 * Created by PhpStorm.
 * User: mmoser
 * Date: 07.10.2016
 * Time: 15:22
 */

namespace  CustomerManagementFramework\ActivityManager;

use Carbon\Carbon;
use CustomerManagementFramework\Factory;
use CustomerManagementFramework\Model\ActivityInterface;
use CustomerManagementFramework\Model\CustomerInterface;

class DefaultActivityManager implements ActivityManagerInterface
{

    protected $disableEvents = false;

    /**
     * @return bool
     */
    public function isDisableEvents() {
        return $this->disableEvents;
    }

    /**
     * @param bool $disableEvents
     * @return $this
     */
    public function setDisableEvents( $disableEvents ) {
        $this->disableEvents = $disableEvents;
        return $this;
    }


    /**
     * Add or update activity in the ActivityStore
     *
     * @param ActivityInterface $activity
     *
     * @return void
     * @throws \Exception
     */
    
    public function trackActivity(ActivityInterface $activity  ) {

        $store = Factory::getInstance()->getActivityStore();

        if(!( $activity->cmfGetActivityDate() instanceof Carbon)) {
            throw new \Exception(get_class($activity) . '::cmfGetActivityDate() needs to return a \Carbon\Carbon instance');
        }

        if(!$activity->getCustomer() instanceof CustomerInterface) {
            $store->deleteActivity($activity);
            return;
        }


        Factory::getInstance()->getSegmentManager()->addCustomerToChangesQueue($activity->getCustomer());

        if(!$activity->cmfIsActive()) {
            $store->deleteActivity($activity);
            return;
        }

        if($entry = $store->getEntryForActivity($activity)) {
            $store->updateActivityInStore($activity, $entry);
        } else {
            $entry = $store->insertActivityIntoStore($activity);

            if( !$this->isDisableEvents() ) {
                $event = new \CustomerManagementFramework\ActionTrigger\Event\NewActivity($activity->getCustomer());
                $event->setActivity($activity);
                $event->setEntry( $entry );
                \Pimcore::getEventManager()->trigger($event->getName(), $event);
            }
        }

        if( !$this->isDisableEvents() ) {
            $event = new \CustomerManagementFramework\ActionTrigger\Event\AfterTrackActivity($activity->getCustomer());
            $event->setActivity($activity);
            $event->setEntry( $entry );
            \Pimcore::getEventManager()->trigger($event->getName(), $event);
        }
    }

    /**
     * Delete an activity from the activty store.
     *
     * @param ActivityInterface $activity
     *
     * @return void
     */

    public function deleteActivity(ActivityInterface $activity) {

        $store = Factory::getInstance()->getActivityStore();

        $store->deleteActivity($activity);
    }
}