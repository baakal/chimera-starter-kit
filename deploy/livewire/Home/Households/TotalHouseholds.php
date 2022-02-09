<?php

namespace App\Http\Livewire\Home\Households;

use App\Http\Livewire\Chart;
use App\Services\QueryBuilder;

class TotalHouseholds extends Chart
{
    public function getCollection(array $filter)
    {
        $l = (new QueryBuilder($this->connection))
            ->select(['COUNT(hh_hh) AS total'])
            ->from([])
            ->get()
            ->first();

        if (!is_null($l)) {
            return number_format($l->total, 0, '', ',');
        }
        return 0;
    }

    protected function setData(array $filter = [])
    {
        // TODO: Implement setData() method.
    }
}
