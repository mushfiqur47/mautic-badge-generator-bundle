<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticBadgeGeneratorBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Templating\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticBadgeGeneratorBundle\Entity\Badge;
use MauticPlugin\MauticBadgeGeneratorBundle\Model\BadgeModel;

class ButtonSubscriber extends CommonSubscriber
{
    private $event;

    private $objectId;

    /**
     * @var BadgeModel
     */
    private $badgeModel;

    /**
     * @var IntegrationHelper
     */
    private $integrationHelper;

    /**
     * ButtonSubscriber constructor.
     *
     * @param BadgeModel        $badgeModel
     * @param IntegrationHelper $integrationHelper
     */
    public function __construct(BadgeModel $badgeModel, IntegrationHelper $integrationHelper)
    {
        $this->badgeModel = $badgeModel;
        $this->integrationHelper = $integrationHelper;
    }



    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    /**
     * @param CustomButtonEvent $event
     */
    public function injectViewButtons(CustomButtonEvent $event)
    {
        $integration = $this->integrationHelper->getIntegrationObject('BadgeGenerator');
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return;
        }

        if (FALSE === strpos($event->getRoute(), 'mautic_contact_')) {
            return;
        }
        if (null === $event->getItem()) {
            return;
        }

        $this->setEvent($event);

        /** @var Lead $object */
        $object = $event->getItem();
        if (method_exists($object, 'getId')) {
            $this->setObjectId($event->getItem()->getId());
        }

        $badges = $this->badgeModel->getEntities();
        /** @var Badge $badge */
        foreach ($badges as $badge) {
            if (!$this->displayBadgeInList($object, $badge)) {
                continue;
            }
            $this->addButtonGenerator(
                $badge->getId(),
                $badge->getName(),
                'fa fa-external-link',
                'contact',
                -5,
                '_blank'
            );
        }


    }

    /**
     * @param Lead  $contact
     * @param Badge $badge
     *
     * @return bool
     */
    private function displayBadgeInList(Lead $contact, Badge $badge)
    {
        if (empty($badge->getProperties()['tags'])) {
            return true;
        }

        $contactTags = $contact->getTags()->getKeys();
        foreach ($contactTags as $contactTag) {
            if (in_array($contactTag, $badge->getProperties()['tags'])) {
                return true;
            }
        }
        return false;
    }


    /**
     * @param        $objectId
     * @param        $btnText
     * @param        $icon
     * @param        $context
     * @param int    $priority
     * @param null   $target
     * @param string $header
     */
    private function addButtonGenerator($objectId, $btnText, $icon, $context, $priority = 1, $target = null, $header = '')
    {
        $event    = $this->getEvent();

        $route    = $this->router->generate(
            'mautic_badge_generator_generate',
            [
                'objectId'     => $objectId,
                'contactId' => $this->getObjectId(),
            ]
        );

        $attr     = [
            'href'        => $route,
            'data-toggle' => 'ajax',
            'data-method' => 'POST',
        ];

        switch ($target){
            case '_blank':
                $attr['data-toggle'] = '';
                $attr['data-method'] = '';
                $attr['target'] = $target;
                break;
            case '#MauticSharedModal':
                $attr['data-toggle'] = 'ajaxmodal';
                $attr['data-method'] = '';
                $attr['data-target'] = $target;
                $attr['data-header'] = $header;
                break;
        }

        $button =
            [
                'attr'      => $attr,
                'btnText'   => $this->translator->trans($btnText),
                'iconClass' => $icon,
                'priority'  => $priority,
            ];
        $event
            ->addButton(
                $button,
                ButtonHelper::LOCATION_LIST_ACTIONS,
                'mautic_'.$context.'_index'
            );
    }

    /**
     * @return CustomButtonEvent
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param mixed CustomButtonEvent
     */
    public function setEvent($event)
    {
        $this->event = $event;
    }

    /**
     * @return mixed
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * @param mixed $objectId
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
    }
}
