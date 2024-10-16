<?php

namespace Maatwebsite\Excel\Tests\Data\Stubs;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Tests\Data\Stubs\Database\User;

class FromUsersQueryWithJoinExport implements FromQuery, WithCustomChunkSize
{
    use Exportable;

    public $query;

    public function __construct()
    {
        $this->query = User::query();
    }

    /**
     * @return Builder|EloquentBuilder|Relation
     */
    public function query()
    {
        return $this->query
            ->join(
                'group_user',
                'group_user.user_id',
                '=',
                'users.id'
            )
            ->select('users.*', 'group_user.group_id as gid');
    }

    /**
     * @return int
     */
    public function chunkSize(): int
    {
        return 10;
    }
}
