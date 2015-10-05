<?php

use DominoPOS\SymmetricDS\Model\Channel;
use DominoPOS\SymmetricDS\Model\Node;
use DominoPOS\SymmetricDS\Model\NodeGroup;
use DominoPOS\SymmetricDS\Model\NodeIdentity;
use DominoPOS\SymmetricDS\Model\NodeSecurity;
use DominoPOS\SymmetricDS\Model\Router;
use DominoPOS\SymmetricDS\Model\Trigger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SymmetricDS extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'sym:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Default Configuration to SymmetricDS database';

    protected $sourceSchemaName;

    protected $tablePrefix;


    protected $tableWhiteList = [
        'carts', // BOX ONLY DATA
        'cart_coupons', // BOX ONLY DATA
        'cart_details', // BOX ONLY DATA
        'sessions', // BOX ONLY DATA
        'migrations', // BOX ONLY DATA
        'settings', // BOX ONLY DATA
        'inboxes', // BOX ONLY DATA
        'mac_addresses', // BOX ONLY DATA
        'failed_jobs', // BOX ONLY DATA
        'tokens', // CLOUD ONLY DATA
        'user_acquisitions', // CLOUD ONLY DATA
    ];

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        $this->sourceSchemaName = DB::getDatabaseName();
        $this->tablePrefix      = DB::getTablePrefix();
        parent::__construct();
    }

    protected function createTrigger(Model $channel, $names = [])
    {
        foreach ($names as $name => $router) {
            $trigger = new Trigger;
            $trigger->trigger_id = $name;
            $trigger->source_catalog_name = $this->sourceSchemaName;
            $trigger->source_table_name  = $this->tablePrefix . $name;
            $trigger->channel()->associate($channel);
            $trigger->save();

            if (! in_array($router, $trigger->routers()->getRelatedIds()))
            {
                $trigger->routers()->attach($router, ['initial_load_order' => 100, 'enabled' => 1]);
            }
        }
    }

    protected function seedRouters()
    {
        $routers = [];

        $router = new Router();
        $router->router_id = 'cloud_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'column';
        $router->router_expression = 'merchant_id=:EXTERNAL_ID';
        if ($router->save()) $routers['cloud_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_to_mall';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'column';
        $router->router_expression = 'mall_id=:EXTERNAL_ID';
        if ($router->save()) $routers['cloud_to_mall'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_to_all_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'default';
        $router->router_expression = null;
        if ($router->save()) $routers['cloud_to_all_merchant'] = $router;


        $router = new Router();
        $router->router_id = 'cloud_merchant_data_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'subquery';
        $router->router_expression = "
            c.external_id in (
                 select :MERCHANT_ID
                 uninon all
                 select parent_id from `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'merchants` m where m.merchant_id = :MERCHANT_ID
                 uninon all
                 select merchant_id from `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'merchants` m where m.parent_id = :MERCHANT_ID
            );
          ";
        if ($router->save()) $routers['cloud_merchant_data_to_merchant'] = $router;


        $router = new Router();
        $router->router_id = 'cloud_product_attr_val_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'product_attributes`
                KEY_COLUMN=product_attribute_id
                LOOKUP_KEY_COLUMN=product_attribute_id
                EXTERNAL_ID_COLUMN=merchant_id
        ';
        if ($router->save()) $routers['cloud_product_attr_val_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_product_pivot_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'products`
            KEY_COLUMN=product_id
            LOOKUP_KEY_COLUMN=product_id
            EXTERNAL_ID_COLUMN=merchant_id
        ';
        if ($router->save()) $routers['cloud_product_pivot_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_retailer_pivot_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'merchants`
            KEY_COLUMN=retailer_id
            LOOKUP_KEY_COLUMN=merchant_id
            EXTERNAL_ID_COLUMN=parent_id
        ';
        if ($router->save()) $routers['cloud_retailer_pivot_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_tenant_pivot_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'merchants`
            KEY_COLUMN=merchant_id
            LOOKUP_KEY_COLUMN=merchant_id
            EXTERNAL_ID_COLUMN=parent_id
        ';
        if ($router->save()) $routers['cloud_tenant_pivot_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_promotion_pivot_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'promotions`
            KEY_COLUMN=promotion_id
            LOOKUP_KEY_COLUMN=promotion_id
            EXTERNAL_ID_COLUMN=merchant_id
        ';
        if ($router->save()) $routers['cloud_promotion_pivot_to_merchant'] = $router;


        $router = new Router();
        $router->router_id = 'cloud_user_merchant_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'subselect';
        $router->router_expression = '
           c.external_id in (
                select (case
                    when length(parent_id) = 16 then parent_id
                    else merchant_id
                    end) as merchant_id from `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'merchants` m where m.user_id = :USER_ID
                union all
                    select acquirer_id from `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'user_acquisitions` ua where ua.user_id = :USER_ID
                union all
                select m.parent_id as merchant_id from `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'employees` e
                    inner join `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'employee_retailer` er on er.employee_id = e.employee_id
                    inner join `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'merchants` m on m.merchant_id = er.retailer_id
                where e.user_id = :USER_ID
            )
        ';
        if ($router->save()) $routers['cloud_user_merchant_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_media_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'subselect';
        $router->router_expression = "
            c.external_id in (
                select m.object_name, (
                    case m.object_name
                    when 'event'     then (select e.merchant_id from `{$this->sourceSchemaName}`.`{$this->tablePrefix}events` e where e.event_id = m.object_id)
                    when 'coupon'    then (select c.merchant_id from `{$this->sourceSchemaName}`.`{$this->tablePrefix}promotions` c where c.promotion_id = m.object_id)
                    when 'promotion' then (select p.merchant_id from `{$this->sourceSchemaName}`.`{$this->tablePrefix}promotions` p where p.promotion_id = m.object_id)
                    when 'merchant'  then m.object_id
                    when 'product'   then (select p.merchant_id from `{$this->sourceSchemaName}`.`{$this->tablePrefix}`products p where p.product_id = m.object_id)
                    when 'widget'    then (select w.merchant_id from `{$this->sourceSchemaName}`.`{$this->tablePrefix}`widgets w where w.widget_id = m.object_id)
                    when 'user'      then null
                    end
                ) as external_id from `{$this->sourceSchemaName}`.`{$this->tablePrefix}media` m where m.media_id = :MEDIA_ID
            )
        ";
        if ($router->save()) $routers['cloud_media_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_employee_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'subselect';
        $router->router_expression = '
            c.external_id in (
                select m.parent_id from `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'employee_retailer` er
                    inner join `'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'merchants` m on m.merchant_id = er.retailer_id
                where er.employee_id = :EMPLOYEE_ID
            )
        ';
        if ($router->save()) $routers['cloud_employee_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_event_translations_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'events`
            KEY_COLUMN=event_id
            LOOKUP_KEY_COLUMN=event_id
            EXTERNAL_ID_COLUMN=merchant_id
        ';
        if ($router->save()) $routers['cloud_event_translations_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_news_translations_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'news`
            KEY_COLUMN=news_id
            LOOKUP_KEY_COLUMN=news_id
            EXTERNAL_ID_COLUMN=mall_id
        ';
        if ($router->save()) $routers['cloud_news_translations_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_promotion_translations_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'promotions`
            KEY_COLUMN=promotion_id
            LOOKUP_KEY_COLUMN=promotion_id
            EXTERNAL_ID_COLUMN=merchant_id
        ';
        if ($router->save()) $routers['cloud_promotion_translations_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_category_translations_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'categories`
            KEY_COLUMN=category_id
            LOOKUP_KEY_COLUMN=category_id
            EXTERNAL_ID_COLUMN=merchant_id
        ';
        if ($router->save()) $routers['cloud_category_translations_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_lucky_draws_pivot_to_mall';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'lucky_draws`
            KEY_COLUMN=lucky_draw_id
            LOOKUP_KEY_COLUMN=lucky_draw_id
            EXTERNAL_ID_COLUMN=mall_id
        ';
        if ($router->save()) $routers['cloud_lucky_draws_pivot_to_mall'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_object_relation_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'objects`
            KEY_COLUMN=main_object_id
            LOOKUP_KEY_COLUMN=object_id
            EXTERNAL_ID_COLUMN=merchant_id
        ';
        if ($router->save()) $routers['cloud_object_relation_to_merchant'] = $router;

        $router = new Router();
        $router->router_id = 'cloud_acquired_user_to_merchant';
        $router->sourceNode()->associate(NodeGroup::getCloud());
        $router->targetNode()->associate(NodeGroup::getMerchant());
        $router->router_type = 'lookuptable';
        $router->router_expression = '
            LOOKUP_TABLE=`'. $this->sourceSchemaName .'`.`'. $this->tablePrefix .'user_acquisitions`
            KEY_COLUMN=user_id
            LOOKUP_KEY_COLUMN=user_id
            EXTERNAL_ID_COLUMN=acquirer_id
        ';
        if ($router->save()) $routers['cloud_acquired_user_to_merchant'] = $router;

        // MERCHANT TO CLOUD
        $router = new Router();
        $router->router_id = 'merchant_to_cloud';
        $router->sourceNode()->associate(NodeGroup::getMerchant());
        $router->targetNode()->associate(NodeGroup::getCloud());
        $router->router_type = 'default';
        $router->router_expression = null;
        if ($router->save()) $routers['merchant_to_cloud'] = $router;

        return $routers;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    protected function seedChannelAndTrigger()
    {

        // Configure Products Channel
        $cProduct = new Channel;
        $cProduct->channel_id = 'products';
        $cProduct->processing_order = 1;
        $cProduct->max_batch_size = 1e3;
        $cProduct->enabled = 1;
        $cProduct->description = 'Product Tables and their relations';

        if ($cProduct->save()) {
            $this->createTrigger($cProduct, [
                'products' => 'cloud_to_merchant',
                'product_retailer' => 'cloud_retailer_pivot_to_merchant',
                'product_variants' => 'cloud_to_merchant',
                'product_attributes' => 'cloud_to_merchant',
                'product_attribute_values' => 'cloud_product_attr_val_to_merchant',
                'pos_quick_products' => 'cloud_to_merchant',
                'categories' => 'cloud_to_merchant',
            ]);
        }


        // Configure General Merchant Data
        $cMerchant = new Channel;
        $cMerchant->channel_id = 'merchant_data';
        $cMerchant->processing_order = 2;
        $cMerchant->max_batch_size = 1e3;
        $cMerchant->enabled = 1;
        $cMerchant->description = 'General merchant data';

        if ($cMerchant->save())
        {
            $this->createTrigger($cMerchant, [
                'merchants'          => 'cloud_merchant_data_to_merchant',
                'merchant_taxes'     => 'cloud_to_merchant',
                'roles'              => 'cloud_to_all_merchant',
                'permissions'        => 'cloud_to_all_merchant',
                'employee_retailer'  => 'cloud_retailer_pivot_to_merchant',
                'employees'          => 'cloud_employee_to_merchant',
                'users'              => 'cloud_user_merchant_to_merchant',
                'user_details'       => 'cloud_user_merchant_to_merchant',
                'apikeys'            => 'cloud_user_merchant_to_merchant',
                'custom_permission'  => 'cloud_user_merchant_to_merchant',
                'user_personal_interest' => 'cloud_user_merchant_to_merchant',
                'permission_role'    => 'cloud_to_all_merchant',
                'personal_interests' => 'cloud_to_all_merchant',
                'countries'          => 'cloud_to_all_merchant',
                'category_merchant'  => 'cloud_tenant_pivot_to_merchant',
                'merchant_translations'  => 'cloud_to_merchant',
                'merchant_languages' => 'cloud_to_merchant',
                'languages'          => 'cloud_to_all_merchant',
                'media'              => 'cloud_media_to_merchant',
                'objects'            => 'cloud_to_merchant',
                'object_relation'    => 'cloud_object_relation_to_merchant',
            ]);
        }

        $cActivity = new Channel;
        $cActivity->channel_id = 'activities';
        $cActivity->processing_order = 3;
        $cActivity->max_batch_size = 1e4;
        $cActivity->enabled = 1;
        $cActivity->description = 'General Activities data';

        if ($cActivity->save())
        {
            $this->createTrigger($cActivity, [
                'activities' => 'merchant_to_cloud'
            ]);
        }

        $cPromotion = new Channel;
        $cPromotion->channel_id = 'promotions';
        $cPromotion->processing_order = 4;
        $cPromotion->max_batch_size = 1e3;
        $cPromotion->enabled = 1;
        $cPromotion->description = 'Promotion Data';

        if ($cPromotion->save())
        {
            $this->createTrigger($cPromotion, [
                'promotions'                => 'cloud_to_merchant',
                'promotion_retailer'        => 'cloud_retailer_pivot_to_merchant',
                'promotion_product'         => 'cloud_product_pivot_to_merchant',
                'promotion_retailer_redeem' => 'cloud_retailer_pivot_to_merchant',
                'promotion_rules'           => 'cloud_promotion_pivot_to_merchant',
                'issued_coupons'            => 'merchant_to_cloud'
            ]);
        }

        $cEvent = new Channel;
        $cEvent->channel_id = 'events';
        $cEvent->processing_order = 5;
        $cEvent->max_batch_size = 1e3;
        $cEvent->enabled = 1;
        $cEvent->description = 'Events Data';

        if ($cEvent->save())
        {
            $this->createTrigger($cEvent, [
                'events' => 'cloud_to_merchant',
                'event_product' => 'cloud_product_pivot_to_merchant',
                'event_retailer' => 'cloud_retailer_pivot_to_merchant'
            ]);
        }

        $cTransaction = new Channel;
        $cTransaction->channel_id = 'transactions';
        $cTransaction->processing_order = 6;
        $cTransaction->max_batch_size = 1e3;
        $cTransaction->enabled = 1;
        $cTransaction->description = 'Transactions Data';

        if ($cTransaction->save())
        {
            $this->createTrigger($cTransaction, [
                'transaction_details'           => 'merchant_to_cloud',
                'transaction_detail_taxes'      => 'merchant_to_cloud',
                'transaction_detail_promotions' => 'merchant_to_cloud',
                'transaction_detail_coupons'    => 'merchant_to_cloud',
                'transactions'                  => 'merchant_to_cloud'
            ]);
        }

        $cWidgets  = new Channel;
        $cWidgets->channel_id = 'widgets';
        $cWidgets->processing_order = 7;
        $cWidgets->max_batch_size = 1e3;
        $cWidgets->enabled = 1;
        $cWidgets->description = 'Widgets Data';

        if ($cWidgets->save())
        {
            $this->createTrigger($cWidgets, [
                'widgets'         => 'cloud_to_merchant',
                'widget_retailer' => 'cloud_retailer_pivot_to_merchant'
            ]);
        }

        $cNews = new Channel;
        $cNews->channel_id = 'news';
        $cNews->processing_order = 8;
        $cNews->max_batch_size = 1e3;
        $cNews->enabled = 1;
        $cNews->description = 'News Data';

        if ($cNews->save())
        {
            $this->createTrigger($cNews, [
                'news'          => 'cloud_to_mall',
                'news_merchant' => 'cloud_to_merchant',
            ]);
        }

        $cTranslation = new Channel;
        $cTranslation->channel_id = 'translations';
        $cTranslation->processing_order = 9;
        $cTranslation->max_batch_size = 1e3;
        $cTranslation->enabled = 1;
        $cTranslation->description = 'Entity Translations Data';

        if ($cTranslation->save())
        {
            $this->createTrigger($cTranslation, [
                'coupon_translations'     => 'cloud_promotion_translations_to_merchant',
                'category_translations'   => 'cloud_category_translations_to_merchant',
                'event_translations'      => 'cloud_event_translations_to_merchant',
                'news_translations'       => 'cloud_news_translations_to_merchant',
                'promotion_translations'  => 'cloud_promotion_translations_to_merchant',
            ]);
        }

        $cLuckyDraw = new Channel;
        $cLuckyDraw->channel_id = 'lucky_draws';
        $cLuckyDraw->processing_order = 10;
        $cLuckyDraw->max_batch_size = 1e3;
        $cLuckyDraw->enabled = 1;
        $cLuckyDraw->description = 'Lucky Draws Objects';


        if ($cLuckyDraw->save())
        {
            $this->createTrigger($cLuckyDraw, [
                'lucky_draws'               => 'cloud_to_mall',
                'lucky_draw_numbers'        => 'cloud_lucky_draws_pivot_to_mall',
                'lucky_draw_winners'        => 'merchant_to_cloud',
                'lucky_draw_receipts'       => 'merchant_to_cloud',
                'lucky_draw_number_receipt' => 'merchant_to_cloud',
            ]);
        }
    }

    protected function seedNodeGroup()
    {
        NodeGroup::seedDefaults();

        $nCloud = new Node;
        $nCloud->node_id = '000000';
        $nCloud->external_id = '000000';
        $nCloud->sync_enabled = 1;
        $nCloud->nodeGroup()->associate(NodeGroup::getCloud());
        $nCloud->save();

        $nsCloud = new NodeSecurity;
        $nsCloud->node_id = $nCloud->node_id;
        $nsCloud->registration_enabled = 1;
        $nsCloud->initial_load_enabled = 1;
        $nsCloud->created_at_node_id = '000000';
        $nsCloud->node_password = hash_hmac('sha256', $nCloud->node_id, $nCloud->external_id);
        $nsCloud->save();

        $nodeIdentity = new NodeIdentity;
        $nodeIdentity->node_id = $nCloud->node_id;
        $nodeIdentity->save();
    }

    public function fire()
    {
        $this->info('Seeding Node Group...');
        $this->seedNodeGroup();

        if ($this->argument('externalId'))
        {
            $merchant = DB::table('merchants')->where('merchant_id', '=', $this->argument('externalId'))->first();

            if (is_null($merchant))
            {
                throw new Exception("Your External ID not associated to any merchants");
            }

            $nMerchant = new Node;
            $nMerchant->node_id = $merchant->merchant_id;
            $nMerchant->sync_enabled = 1;
            $nMerchant->external_id = $merchant->merchant_id;
            $nMerchant->nodeGroup()->associate(NodeGroup::getMerchant());
            $nMerchant->save();

            $nsMerchant = new NodeSecurity;
            $nsMerchant->node_id = $nMerchant->node_id;
            $nsMerchant->registration_enabled = 1;
            $nsMerchant->initial_load_enabled = 1;
            $nsMerchant->created_at_node_id = '000000';
            $nsMerchant->node_password = hash_hmac('sha256', $nMerchant->node_id, $nMerchant->external_id);
            $nsMerchant->save();

            return true;
        }

        $this->info('Seeding Router...');
        $this->seedRouters();
        $this->info('Seeding Channel and Trigger...');
        $this->seedChannelAndTrigger();


        if ($this->option('inspect'))
        {
            $whiteListTables = array_map(function ($i) {
                return $this->tablePrefix . $i;
            }, $this->tableWhiteList);

            $sourceTables = DB::table(DB::raw('information_schema.tables'))->where('table_schema', '=', $this->sourceSchemaName)->lists('table_name');
            $sourceTables = array_filter($sourceTables, function ($i) use ($whiteListTables) {
                return !in_array($i, $whiteListTables);
            });
            $tTables = Trigger::lists('source_table_name');
            $diffTables = array_diff($sourceTables, $tTables);
            $this->info('Result: ');
            $this->info('Table Count: ' . DB::table(DB::raw('information_schema.tables'))->where('table_schema', '=', $this->sourceSchemaName)->count('table_name'));
            $this->info('Trigger Count: ' . Trigger::count());
            $this->info('Ignored Table Count: ' . count($whiteListTables));
            $this->info('Table Diff: ' . json_encode($diffTables));
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('externalId', InputArgument::OPTIONAL, 'Force Rewrite the configurations'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('inspect', null, InputOption::VALUE_OPTIONAL, 'Inspect trigger and table', null),
        );
    }

}
