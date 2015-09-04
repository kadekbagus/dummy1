<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use Category;
use Config;
use DB;
use EventModel;
use Exception;
use Lang;
use Product;
use Promotion;
use Validator;
use View;
use Widget;

class HomeController extends MobileCIAPIController
{
    /**
     * GET - Welcome page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getWelcomeView()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $cartdata = $this->getCartForToolbar();
            $user_email = $user->user_email;

            return View::make('mobile-ci.welcome', array('retailer'=>$retailer, 'user'=>$user, 'cartdata' => $cartdata, 'user_email' => $user_email));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Thank you page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getThankYouView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $activityPageNotes = sprintf('Page viewed: %s', 'Thank You Page');
            $activityPage->setUser($user)
                ->setActivityName('view_page_thank_you')
                ->setActivityNameLong('View (Thank You Page)')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.thankyoucart', array('retailer' => $retailer));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Thank You Page');
            $activityPage->setUser($user)
                ->setActivityName('view_page_thank_you')
                ->setActivityNameLong('View (Thank You Page) Failed')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Home page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getHomeView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $random_products = Product::with('media')
                ->where(function($q) use ($retailer) {
                    $q->where(function($q2) use($retailer) {
                        $q2->where('is_all_retailer', 'Y');
                        $q2->where('merchant_id', $retailer->parent->merchant_id);
                    });
                    $q->orWhere(function($q2) use ($retailer) {
                        $q2->where('is_all_retailer', 'N');
                        $q2->whereHas('retailers', function($q3) use($retailer) {
                            $q3->where('product_retailer.retailer_id', $retailer->merchant_id);
                        });
                    });
                })
                ->active()
                ->orderBy('created_at', 'desc')
                ->take(20)
                ->get();

            $new_products = Product::with('media')
                ->where(function($q) use ($retailer) {
                    $q->where(function($q2) use($retailer) {
                        $q2->where('is_all_retailer', 'Y');
                        $q2->where('merchant_id', $retailer->parent->merchant_id);
                    });
                    $q->orWhere(function($q2) use ($retailer) {
                        $q2->where('is_all_retailer', 'N');
                        $q2->whereHas('retailers', function($q3) use($retailer) {
                            $q3->where('product_retailer.retailer_id', $retailer->merchant_id);
                        });
                    });
                })
                ->active()
                ->where(function($q) {
                    $q->where(function($q2) {
                        $q2->where('new_from', '<=', Carbon::now())->where('new_until', '>=', Carbon::now());
                    });
                    $q->orWhere(function($q2) {
                        $q2->where('new_from', '<=', Carbon::now())->where('new_from', '<>', '0000-00-00 00:00:00');
                    });
                })
                ->orderBy('created_at', 'desc')
                ->take(20)
                ->get();

            // $promotion = Promotion::active()->where('is_coupon', 'N')->where('merchant_id', $retailer->parent_id)->whereHas(
            //     'retailers',
            //     function ($q) use ($retailer) {
            //         $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            //     }
            // )
            //     ->where(
            //         function ($q) {
            //             $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(
            //                 function ($qr) {
            //                     $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
            //                 }
            //             );
            //         }
            //     )
            //     ->orderBy(DB::raw('RAND()'))->first();

            $promo_products = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid, prod.product_id as prodid, p.image as promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
                left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_rule_id = propro.promotion_rule_id AND object_type = "discount")
                left join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                left join ' . DB::getTablePrefix() . 'products prod on
                    (
                        (pr.discount_object_type="product" AND propro.product_id = prod.product_id)
                        OR
                        (
                            (pr.discount_object_type="family") AND
                            ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND
                            ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                            ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                            ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                            ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                        )
                    )

                WHERE prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid)
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );
            
            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
                left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_rule_id = propro.promotion_rule_id AND object_type = "discount")
                left join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
                left join ' . DB::getTablePrefix() . 'products prod on
                (
                    (pr.discount_object_type="product" AND propro.product_id = prod.product_id)
                    OR
                    (
                        (pr.discount_object_type="family") AND
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE ic.expired_date >= "' . Carbon::now() . '" AND ic.user_id = :userid AND ic.expired_date >= "' . Carbon::now() . '" AND (prr.retailer_id = :retailerid OR (p.is_all_retailer_redeem = "Y" AND p.merchant_id = :merchantid))
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id)
            );

            if (empty(\Cookie::get('event'))) {
                $event_store = array();
            } else {
                $event_store = \Cookie::get('event');
            }

            $events = EventModel::active()
                ->where(function($q) use ($retailer) {
                    $q->where(function($q2) use($retailer) {
                        $q2->where('is_all_retailer', 'Y');
                        $q2->where('merchant_id', $retailer->parent->merchant_id);
                    });
                    $q->orWhere(function($q2) use ($retailer) {
                        $q2->where('is_all_retailer', 'N');
                        $q2->whereHas('retailers', function($q3) use($retailer) {
                            $q3->where('event_retailer.retailer_id', $retailer->merchant_id);
                        });
                    });
                })
                ->where(
                    function ($q) {
                        $q->where(
                            function ($q2) {
                                $q2->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now());
                            }
                        );
                        $q->orWhere(
                            function ($q2) {
                                $q2->where('begin_date', '<=', Carbon::now())->where('is_permanent', 'Y');
                            }
                        );
                    }
                );

            if (! empty($event_store)) {
                foreach ($event_store as $event_idx) {
                    $events->where('event_id', '!=', $event_idx);
                }
            }

            $events = $events->orderBy('events.event_id', 'DESC')->first();
            // dd($events);
            $event_families = array();
            if (! empty($events)) {
                if ($events->link_object_type == 'family') {
                    if (! empty($events->link_object_id1)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id1)->active()->first();
                    }
                    if (! empty($events->link_object_id2)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id2)->active()->first();
                    }
                    if (! empty($events->link_object_id3)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id3)->active()->first();
                    }
                    if (! empty($events->link_object_id4)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id4)->active()->first();
                    }
                    if (! empty($events->link_object_id5)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id5)->active()->first();
                    }
                }
            }

            $event_family_url_param = '';
            for ($i = 0; $i <= count($event_families) - 1; $i++) {
                $event_family_url_param = $event_family_url_param . 'f' . ($i + 1) . '=' . $event_families[$i]->category_id;
                if ($i < count($event_families) - 1) {
                    $event_family_url_param = $event_family_url_param . '&';
                }
            }

            if (! empty($events)) {
                $event_store[] = $events->event_id;
                \Cookie::queue('event', $event_store, 1440);
            }

            $cartitems = $this->getCartForToolbar();

            $widgets = Widget::with('media')
                ->active()
                ->where('merchant_id', $retailer->parent->merchant_id)
                ->whereHas(
                    'retailers',
                    function ($q) use ($retailer) {
                        $q->where('retailer_id', $retailer->merchant_id);
                    }
                )
                ->orderBy('widget_order', 'ASC')
                ->groupBy('widget_type')
                ->take(4)
                ->get();

            $activityPageNotes = sprintf('Page viewed: %s', 'Home');
            $activityPage->setUser($user)
                ->setActivityName('view_page_home')
                ->setActivityNameLong('View (Home Page)')
                ->setObject(null)
                ->setNotes($activityPageNotes)
                ->setModuleName('Widget')
                ->responseOK()
                ->save();

            return View::make('mobile-ci.home',
                array(
                    'page_title' => Lang::get('mobileci.page_title.home'),
                    'retailer' => $retailer,
                    'random_products' => $random_products,
                    'new_products' => $new_products,
                    'promo_products' => $promo_products,
                    // 'promotion' => $promotion,
                    'cartitems' => $cartitems,
                    'coupons' => $coupons,
                    'events' => $events,
                    'widgets' => $widgets,
                    'event_families' => $event_families,
                    'event_family_url_param' => $event_family_url_param
                )
            )->withCookie($event_store);
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Home');
            $activityPage->setUser($user)
                ->setActivityName('view_page_home')
                ->setActivityNameLong('View (Home Page) Failed')
                ->setObject(null)
                ->setModuleName('Widget')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }
}
