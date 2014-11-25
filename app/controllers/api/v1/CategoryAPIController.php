<?php
/**
 * An API controller for managing Category.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class CategoryAPIController extends ControllerAPI
{
    /**
     * POST - Update Category
     *
     * @author <Kadek> <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `category_id`           (required) - Category ID
     * @param string     `category_name`         (required) - Category name
     * @param integer    `parent_id`             (required) - Parent ID
     * @param integer    `category_order`        (optional) - Category order
     * @param string     `description`           (optional) - Description
     * @param string     `status`                (optional) - Status
     * @param integer    `modified_by`	         (optional) - Modified By
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateCategory()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.category.postupdatecategory.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.category.postupdatecategory.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.category.postupdatecategory.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_category')) {
                Event::fire('orbit.category.postupdatecategory.authz.notallowed', array($this, $user));
                $updateCategoryLang = Lang::get('validation.orbit.actionlist.update_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateCategoryLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.category.postupdatecategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

			$category_id = OrbitInput::post('category_id');
            $category_name = OrbitInput::post('category_name');
            $parent_id = OrbitInput::post('parent_id');
            $category_order = OrbitInput::post('category_order');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');
            $modified_by = OrbitInput::post('modified_by');

            $validator = Validator::make(
                array(
                	'category_id'       => $category_id,
                	'category_name'     => $category_name,
                    'parent_id'         => $parent_id,
                ),
                array(
                	'category_id'       => 'required|numeric|orbit.empty.category',
                    'category_name'     => 'required',
                    'parent_id'         => 'required|numeric|orbit.empty.category',
                )
            );

            Event::fire('orbit.category.postupdatecategory.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.category.postupdatecategory.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedcategory = Category::find($category_id);
            $updatedcategory->category_name = $category_name;
            $updatedcategory->parent_id = $parent_id;
            $updatedcategory->category_order = $category_order;
            $updatedcategory->description = $description;
            $updatedcategory->status = $status;;
            $updatedcategory->modified_by = $this->api->user->user_id;

            Event::fire('orbit.category.postupdatecategory.before.save', array($this, $updatedcategory));

            $updatedcategory->save();

            Event::fire('orbit.category.postupdatecategory.after.save', array($this, $updatedcategory));
            $this->response->data = $updatedcategory->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.category.postupdatecategory.after.commit', array($this, $updatedcategory));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.category.postupdatecategory.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.category.postupdatecategory.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.category.postupdatecategory.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.category.postupdatecategory.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);

    }


    /**
     * GET - Search Category
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sort_by`               (optional) - column order by
     * @param string   `sort_mode`             (optional) - asc or desc
     * @param integer  `category_id`           (optional) - Category ID
     * @param string   `category_name`         (optional) - Category name
     * @param string   `category_name_like`    (optional) - Category name like
     * @param integer  `parent_id`             (optional) - Parent ID
     * @param integer  `category_order`        (optional) - Category order
     * @param string   `description`           (optional) - Description
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCategory()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.category.getsearchcategory.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.category.getsearchcategory.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.category.getsearchcategory.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_category')) {
                Event::fire('orbit.category.getsearchcategory.authz.notallowed', array($this, $user));
                $viewCategoryLang = Lang::get('validation.orbit.actionlist.view_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCategoryLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.category.getsearchcategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:category_name,parent_id,category_order,registered_date',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            Event::fire('orbit.category.getsearchcategory.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.category.getsearchcategory.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $categories = Category::excludeDeleted();

            // Filter category by Ids
            OrbitInput::get('category_id', function($categoryIds) use ($categories)
            {
                $categories->whereIn('categories.category_id', $categoryIds);
            });

            // Filter category by category name
            OrbitInput::get('category_name', function($categoryname) use ($categories)
            {
                $categories->whereIn('categories.category_name', $categoryname);
            });

            // Filter category by matching category name pattern
            OrbitInput::get('category_name_like', function($categoryname_like) use ($categories)
            {
                $categories->where('categories.category_name', 'like', "%$category_name%");
            });

            // Filter category by parent Ids
            OrbitInput::get('parent_id', function($parentIds) use ($categories)
            {
                $categories->whereIn('categories.parent_id', $parentIds);
            });

            // Filter category by category order
            OrbitInput::get('category_order', function($categoryorder) use ($categories)
            {
                $categories->whereIn('categories.category_order', $categoryorder);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_categories = clone $categories;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
            {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $categories->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $categories)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $categories->skip($skip);

            // Default sort by
            $sortBy = 'categories.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'categories.created_at',
                    'category_name'	    => 'categories.category_name',
                    'parent_id'         => 'categories.parent_id',
                    'category_order'    => 'categories.category_order'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $categories->orderBy($sortBy, $sortMode);

            $totalCategories = $_categories->count();
            $listOfCategories = $categories->get();

            $data = new stdclass();
            $data->total_records = $totalCategories;
            $data->returned_records = count($listOfCategories);
            $data->records = $listOfCategories;

            if ($totalCategories === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.categories');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.category.getsearchcategory.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.category.getsearchcategory.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.category.getsearchcategory.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            Event::fire('orbit.category.getsearchcategory.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.category.getsearchcategory.before.render', array($this, &$output));

        return $output;
    }


   	protected function registerCustomValidation()
    {
        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();

            if (empty($category)) {
                return FALSE;
            }

            App::instance('orbit.empty.category', $category);

            return TRUE;
        });
    }
}