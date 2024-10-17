<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SuperAdminModuleScope implements Scope
{

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->where($model->getTable() . '.is_superadmin', '=', 0);

        // AFV (S8): the databackd_* modules only exist as package modules - they have no permissions, so they stay
        // out of the roles/permissions pages. Package screens list them with Module::databackdModules().
        $builder->where($model->getTable() . '.module_name', 'not like', 'databackd\\_%');
    }

}
