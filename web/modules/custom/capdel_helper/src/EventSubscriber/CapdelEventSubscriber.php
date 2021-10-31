<?php

namespace Drupal\capdel_helper\EventSubscriber;

use Drupal\capdel_helper\Helpers\LandingPageHelper;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber CapdelEventSubscriber.
 * Subscriber reacts on the REQUEST events, and add query param to the
 * landing page content type
 */
class CapdelEventSubscriber implements EventSubscriberInterface {

    public const VID_ID = [
      'tax_menu_types' => 'evenement_tous=',
      'tax_destination' => 'destination=',
    ];

    /**
     * This is the function that sets the query param to be used in a Facet block
     */
    public function onRespond(GetResponseEvent $event) {
        $node = \Drupal::routeMatch()->getParameter('node');
        if(!$node instanceof Node){
          return;
        }

        if(isset($node)){
            $nodeType = $node->getType();
            // only for the landing pages content
            if($nodeType !== "landing_page") {
                return;
            }

            //add parameter to correctly setup session
            LandingPageHelper::addReferencedTaxonomieRequestParam($node);

            // set the session attribute, if there is no session cat there
            $this->setLPSessionAttribute($event->getRequest());
        }
        else {
            //need to get the search page and it is not a node one
            $viewId = \Drupal::routeMatch()->getParameter('view_id');
            $displayId = \Drupal::routeMatch()->getParameter('display_id');

            // get the search page view and display id from params
            $searchPage = (isset($viewId) && $viewId === 'search_results_page')
              && (isset($displayId) && $displayId === 'search_page');

            // if we are on the search page
            if($searchPage) {
                // if we are landing on the first search page and we should redirect
                // the user to the landing page.
                if($this->shouldRedirectToLandingPage($event->getRequest())) {
                    $eventId = $this->getCategoryIdFromQuery($event->getRequest()->query);
                    if($eventId !== false) {
                        $session = $event->getRequest()->getSession();
                        if($session) {
                            $urlValue = $session->get('lp_'.$eventId);
                            if($urlValue) {
                                $response = new RedirectResponse($urlValue);
                                return $response->send();
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() {
        $events[KernelEvents::REQUEST][] = ['onRespond', 27];
        return $events;
    }

    /**
     * Returns true if the user was on the filter page and he should be returned
     * to the first page of the search (and we should redirect him to the landing
     * page, got from the session).
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return bool
     */
    private function shouldRedirectToLandingPage(\Symfony\Component\HttpFoundation\Request $request) {
        $currentFiltersParams = $request->query->get('f');
        $isNotFirstSearchPage = ($request->query->get('page') !== null) && $request->query->get('page') != 0;
        $referer = $request->server->get('HTTP_REFERER');

        if($referer !== null) {
            $previousFilterParams = parse_url($referer,PHP_URL_QUERY);
            if($previousFilterParams) {
                parse_str($previousFilterParams,$previousFilterParams);
            }
            $isFinPreviousParams = isset($previousFilterParams['f']);
            $isFinCurrentParams = \count($currentFiltersParams) > 0;

            //we have the F in previous parameters, indicating that
            //we are returning from facet filter, but we do not have it now
            //so we must redirect to the first landing page
            if($isFinPreviousParams && !$isFinCurrentParams && !$isNotFirstSearchPage) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set the session attribute.
     * Distinct based on the ID of the category, only first value is allowed,
     * so we are only saving first value (should be first landing page)
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    private function setLPSessionAttribute(\Symfony\Component\HttpFoundation\Request $request) {
        $eventId = $this->getCategoryIdFromQuery($request->query);
        if($eventId !== false) {
            $session = $request->getSession();
            if($session) {
                $previousValue = $session->get('lp_'.$eventId);
                //only first page allowed here
                if($previousValue === null) {
                    $session->set('lp_'.$eventId, $request->getRequestUri());
                }
            }
        }
    }

    /**
     * Get the value to get/save proper ID for session param.
     * Either event Type ID or destination ID
     * @param $query
     *
     * @return bool
     */
    private function getCategoryIdFromQuery($query) {
        $eventId = $query->get('evenement_tous');
        $destId = $query->get('destination');

        //get the id of the tax, either event or destination
        if($eventId === 'All' || $eventId === null) {
            $eventId = $destId;
        }

        //if still null, return and do nothing
        if($eventId === 'All' || $eventId === null) {
            return false;
        }
        return $eventId;
    }
}
