<?php

namespace Backpack\NewsCRUD\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
// VALIDATION: change the requests to match your own file names if you need form validation
use Backpack\NewsCRUD\app\Http\Requests\ArticleRequest as StoreRequest;
use Backpack\NewsCRUD\app\Http\Requests\ArticleRequest as UpdateRequest;
use Artisan;
use App\Http\Controllers\SyncController;
use App\Models\CategoryModel;

class ArticleCrudController extends CrudController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function setup()
    {
        /*
        |--------------------------------------------------------------------------
        | BASIC CRUD INFORMATION
        |--------------------------------------------------------------------------
        */
        $this->crud->setModel("Backpack\NewsCRUD\app\Models\Article");
        $this->crud->setRoute(config('backpack.base.route_prefix', 'admin').'/article');
        $this->crud->setEntityNameStrings('article', 'Tin Tức');
        $this->crud->orderBy('id', 'desc');

        /*
        |--------------------------------------------------------------------------
        | COLUMNS AND FIELDS
        |--------------------------------------------------------------------------
        */

        // ------ CRUD COLUMNS
//        $this->crud->addColumn([
//                                'name' => 'created_at',
//                                'label' => 'Date',
//                                'type' => 'date',
//        ]);
        $this->crud->addFilter([ // daterange filter
            'type' => 'date_range',
            'name' => 'from_to',
            'label'=> 'Hiển thị theo thời gian'
        ],
            false,
            function($value) {
                $dates = json_decode(htmlspecialchars_decode($value, ENT_QUOTES));
                $this->crud->addClause('whereDate', 'created_at', '>=', $dates->from);
                $this->crud->addClause('whereDate', 'created_at', '<=', $dates->to);
            });
        $this->crud->addFilter([ // select2_multiple filter
            'name' => 'roles',
//            'type' => 'dropdown',
            'type' => 'select2_multiple',
            'label'=> 'Danh mục',
            'placeholder' => 'Tìm Danh mục tin tức'
        ], function () {
            return CategoryModel::orderBy('name')->get()->pluck('name', 'id')->toArray();
        }, function ($values) {
            $values = json_decode(htmlspecialchars_decode($values, ENT_QUOTES));
            if (!empty($values)) {
                $this->crud->addClause('whereIn', 'category_id', $values);
            }
        });
        $this->crud->enableBulkActions();
        $this->crud->addBulkDeleteButton();
        $this->crud->addColumn([
            'name'  => 'id',
            'label' => 'ID',
            'type' => 'closure',
            'function' => function($entry) {
                return '<a href="/'.$entry->category->slug.'/'.$entry->slug.'" target="_blank">'.$entry->id.'</a>';
            },
        ]);
        $this->crud->addColumn([
            'name' => 'title',
            'label' => 'Tiêu đề',
        ]);
        $this->crud->addColumn([
            'name' => 'featured',
            'label' => 'Nỗi bật',
            'type' => 'check',
        ]);

        $this->crud->addColumn([
            'label' => 'Danh mục',
//                                'type' => 'closure',
            'type' => 'select',
            'name' => 'category_id',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => "Backpack\NewsCRUD\app\Models\Category",
//                                'function' => function($entry) {
//                                    return $entry->category->name;
//                                }
        ]);
        $this->crud->addColumn([
            'name' => 'status',
            'label' => 'Tình trạng',
        ]);
        $this->crud->addColumn([
            'name' => 'views',
            'label' => 'Lượt xem',
            'type' => 'number',
        ]);
        // ------ CRUD FIELDS
        $this->crud->addField([    // TEXT
            'name' => 'title',
            'label' => 'Tiêu đề',
            'type' => 'text',
            'placeholder' => 'Nhập tiêu đề tin tức',
        ]);
        $this->crud->addField([
            'name' => 'slug',
            'label' => 'Slug (URL)',
            'type' => 'text',
            'hint' => 'Sẽ được tạo tự động từ tiêu đề của bạn, nếu để trống.',
            // 'disabled' => 'disabled'
        ]);
        $this->crud->addField([
            'name' => 'short_content',
            'label' => 'Nội dung ngắn',
            'type' => 'textarea',
            'placeholder' => 'Nội dung ngắn thể hiện',
        ]);

        $this->crud->addField([    // WYSIWYG
            'name' => 'content',
            'label' => 'Nội dung',
            'type' => 'ckeditor',
            'placeholder' => 'Your textarea text here',
        ]);

        $this->crud->addField([    // Image
            'name' => 'image',
            'label' => 'Ảnh đại diện',
            'type' => 'browse',
        ]);
        $this->crud->addField([    // SELECT
            'label' => 'Danh mục',
            'type' => 'select2',
            'name' => 'category_id',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => "Backpack\NewsCRUD\app\Models\Category",
        ]);
        $this->crud->addField([       // Select2Multiple = n-n relationship (with pivot table)
            'label' => 'Tags',
            'type' => 'select2_multiple',
            'name' => 'tags', // the method that defines the relationship in your Model
            'entity' => 'tags', // the method that defines the relationship in your Model
            'attribute' => 'name', // foreign key attribute that is shown to user
            'model' => "Backpack\NewsCRUD\app\Models\Tag", // foreign key model
            'pivot' => true, // on create&update, do you need to add/delete pivot table entries?
        ]);
        $this->crud->addField([    // ENUM
            'name' => 'status',
            'label' => 'Tình trạng',
            'type' => 'enum',
        ]);
        $this->crud->addField([    // CHECKBOX
            'name' => 'featured',
            'label' => 'Nỗi bật',
            'type' => 'checkbox',
        ]);

        $this->crud->enableAjaxTable();
    }

    public function store(StoreRequest $request)
    {
        $this->crud->hasAccessOrFail('create');
        // fallback to global request instance
        if (is_null($request)) {
            $request = \Request::instance();
        }

        // replace empty values with NULL, so that it will work with MySQL strict mode on
        foreach ($request->input() as $key => $value) {
            if (empty($value) && $value !== '0') {
                $request->request->set($key, null);
            }
        }
        // insert item in the db
        $item = $this->crud->create($request->except(['save_action', '_token', '_method', 'current_tab']));
        $this->data['entry'] = $this->crud->entry = $item;

        // show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // save the redirect choice for next time
        $this->setSaveAction();
        $sync = new SyncController();
        $sync->homeTinTuc();
        return $this->performSaveAction($item->getKey());
    }

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        // get entry ID from Request (makes sure its the last ID for nested resources)
        $sync = new SyncController();
        $sync->homeTinTuc();
        $id = $this->crud->getCurrentEntryId() ?? $id;
        return $this->crud->delete($id);
    }

    public function update(UpdateRequest $request)
    {
        $this->crud->hasAccessOrFail('update');

        // fallback to global request instance
        if (is_null($request)) {
            $request = \Request::instance();
        }

        // replace empty values with NULL, so that it will work with MySQL strict mode on
        foreach ($request->input() as $key => $value) {
            if (empty($value) && $value !== '0') {
                $request->request->set($key, null);
            }
        }

        // update the row in the db
        $item = $this->crud->update($request->get($this->crud->model->getKeyName()),
            $request->except('save_action', '_token', '_method', 'current_tab'));
        $this->data['entry'] = $this->crud->entry = $item;

        // show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // save the redirect choice for next time
        $this->setSaveAction();
        $sync = new SyncController();
        $sync->homeTinTuc();
        return $this->performSaveAction($item->getKey());
    }
}
