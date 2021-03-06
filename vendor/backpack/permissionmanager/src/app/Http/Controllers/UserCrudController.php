<?php

namespace Backpack\PermissionManager\app\Http\Controllers;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Requests\CrudRequest;
use Backpack\PermissionManager\app\Http\Requests\UserStoreCrudRequest as StoreRequest;
use Backpack\PermissionManager\app\Http\Requests\UserUpdateCrudRequest as UpdateRequest;
use App\Role;
use App\Permission;
use App\PermissionUserModel;

class UserCrudController extends CrudController
{
    public function setup()
    {
        /*
        |--------------------------------------------------------------------------
        | BASIC CRUD INFORMATION
        |--------------------------------------------------------------------------
        */
        $this->crud->setModel(config('backpack.permissionmanager.user_model'));
        $this->crud->setEntityNameStrings(trans('backpack::permissionmanager.user'), trans('backpack::permissionmanager.users'));
        $this->crud->setRoute(config('backpack.base.route_prefix').'/user');
        $this->crud->enableAjaxTable();


        $this->crud->addFilter([ // select2_multiple filter
            'name' => 'roles',
//            'type' => 'dropdown',
            'type' => 'select2_multiple',
            'label'=> 'Roles',
            'placeholder' => 'Tìm phân quyền truy cập (Roles)'
        ], function () {
            return Role::orderBy('name')->get()->pluck('name', 'id')->toArray();
        }, function ($values) {
            $values = json_decode(htmlspecialchars_decode($values, ENT_QUOTES));
            if (!empty($values)) {
                // $this->crud->addClause('where', 'roles.name', 'IN', $values);
                $this->crud->query = $this->crud->query->whereHas('roles',
                    function ($query) use ($values) {
                        $query->whereIn('roles.id', $values);
                    });
            }
        });
        $this->crud->addFilter([ // select2_multiple filter
            'name' => 'permissions',
            'type' => 'select2_multiple',
            'label'=> 'Extra Permissions',
            'placeholder' => 'Tìm phân quyền truy cập (Extra Permissions)'
        ], function () {
            return Permission::orderBy('name')->get()->pluck('name', 'id')->toArray();
        }, function ($values) {
            $values = json_decode(htmlspecialchars_decode($values, ENT_QUOTES));
            if (!empty($values)) {
                $this->crud->query = $this->crud->query->whereHas('permissions',
                    function ($query) use ($values) {
                        $query->whereIn('permissions.id', $values);
                    });
            }
        });


        // Columns.
        $this->crud->setColumns([
            [
                'name'  => 'name',
                'label' => trans('backpack::permissionmanager.name'),
                'type'  => 'text',
            ],
            [
                'name'  => 'email',
                'label' => trans('backpack::permissionmanager.email'),
                'type'  => 'email',
            ],
            [
                'name'  => 'phone',
                'label' => 'Số điện thoại',
            ],
            [
                'name'  => 'province',
                'label' => 'Tỉnh',
            ],
            [
                'name'  => 'district',
                'label' => 'Quận',
            ],
            [
                'name'  => 'cash_curent',
                'label' => 'Tiền mặt',
                'type' => 'number',
            ],
            [
                'name'  => 'point_current',
                'label' => 'Điểm',
                'type' => 'number',
            ],
            [
                'name'  => 'user_type_id',
                'label' => 'Loại tài khoản',
                'type' => 'select',
                'entity' => 'userTypeName',
                'attribute' => 'name',
                'model' => "App\Models\UserTypeModel",
            ],

            [ // n-n relationship (with pivot table)
               'label'     => trans('backpack::permissionmanager.roles'), // Table column heading
               'type'      => 'select_multiple',
               'name'      => 'roles', // the method that defines the relationship in your Model
               'entity'    => 'roles', // the method that defines the relationship in your Model
               'attribute' => 'name', // foreign key attribute that is shown to user
               'model'     => config('laravel-permission.models.role'), // foreign key model
            ],
//            [ // n-n relationship (with pivot table)
//               'label'     => trans('backpack::permissionmanager.extra_permissions'), // Table column heading
//               'type'      => 'select_multiple',
//               'name'      => 'permissions', // the method that defines the relationship in your Model
//               'entity'    => 'permissions', // the method that defines the relationship in your Model
//               'attribute' => 'name', // foreign key attribute that is shown to user
//               'model'     => config('laravel-permission.models.permission'), // foreign key model
//            ],
        ]);

        // Fields
        $this->crud->addFields([
            [
                'name'  => 'name',
                'label' => trans('backpack::permissionmanager.name'),
                'type'  => 'text',
            ],
            [
                'name'  => 'phone',
                'label' => 'Số điện thoại',
                'type'  => 'text',
                'wrapperAttributes' => [
                    'class' => 'form-group col-md-6',
                ],
            ],
            [
                'name'  => 'cash_curent',
                'label' => 'Số tiền hiện tại',
                'type'  => 'number',
                'wrapperAttributes' => [
                    'class' => 'form-group col-md-3',
                ],
            ],

            [
                'name'  => 'point_current',
                'label' => 'Số diểm hiện tại',
                'type'  => 'number',
                'wrapperAttributes' => [
                    'class' => 'form-group col-md-3',
                ],
            ],
            [
                'name'  => 'email',
                'label' => trans('backpack::permissionmanager.email'),
                'type'  => 'email',
            ],
            [
                'label' => 'Loại tài khoản',
                'type' => 'select',
                'name' => 'user_type_id',
                'entity' => 'userTypeName',
                'attribute' => 'name',
                'model' => "App\Models\UserTypeModel",
            ],
            [
                'name'  => 'password',
                'label' => trans('backpack::permissionmanager.password'),
                'type'  => 'password',
            ],
            [
                'name'  => 'password_confirmation',
                'label' => trans('backpack::permissionmanager.password_confirmation'),
                'type'  => 'password',
            ],
            [
            // two interconnected entities
            'label'             => trans('backpack::permissionmanager.user_role_permission'),
            'field_unique_name' => 'user_role_permission',
            'type'              => 'checklist_dependency',
            'name'              => 'roles_and_permissions', // the methods that defines the relationship in your Model
            'subfields'         => [
                    'primary' => [
                        'label'            => trans('backpack::permissionmanager.roles'),
                        'name'             => 'roles', // the method that defines the relationship in your Model
                        'entity'           => 'roles', // the method that defines the relationship in your Model
                        'entity_secondary' => 'permissions', // the method that defines the relationship in your Model
                        'attribute'        => 'name', // foreign key attribute that is shown to user
                        'model'            => config('laravel-permission.models.role'), // foreign key model
                        'pivot'            => true, // on create&update, do you need to add/delete pivot table entries?]
                        'number_columns'   => 3, //can be 1,2,3,4,6
                    ],
                    'secondary' => [
                        'label'          => ucfirst(trans('backpack::permissionmanager.permission_singular')),
                        'name'           => 'permissions', // the method that defines the relationship in your Model
                        'entity'         => 'permissions', // the method that defines the relationship in your Model
                        'entity_primary' => 'roles', // the method that defines the relationship in your Model
                        'attribute'      => 'name', // foreign key attribute that is shown to user
                        'model'          => config('laravel-permission.models.permission'), // foreign key model
                        'pivot'          => true, // on create&update, do you need to add/delete pivot table entries?]
                        'number_columns' => 3, //can be 1,2,3,4,6
                    ],
                ],
            ],
        ]);
    }

    /**
     * Store a newly created resource in the database.
     *
     * @param StoreRequest $request - type injection used for validation using Requests
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreRequest $request)
    {
        $this->handlePasswordInput($request);

        return parent::storeCrud($request);
    }

    /**
     * Update the specified resource in the database.
     *
     * @param UpdateRequest $request - type injection used for validation using Requests
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateRequest $request)
    {
        $this->handlePasswordInput($request);

        return parent::updateCrud($request);
    }

    /**
     * Handle password input fields.
     *
     * @param CrudRequest $request
     */
    protected function handlePasswordInput(CrudRequest $request)
    {
        // Remove fields not present on the user.
        $request->request->remove('password_confirmation');

        // Encrypt password if specified.
        if ($request->input('password')) {
            $request->request->set('password', bcrypt($request->input('password')));
        } else {
            $request->request->remove('password');
        }
    }
}
