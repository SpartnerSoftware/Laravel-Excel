<?php

namespace Maatwebsite\Excel\Concerns;

if (trait_exists('\Illuminate\Bus\Batchable')) {
    trait BatchableTrait
    {
        use \Illuminate\Bus\Batchable;

        public function batchCancelled()
        {
            $batch = $this->batch();

            return $batch !== null && method_exists($batch, 'cancelled') && $batch->cancelled();
        }
    }
} else {
    trait BatchableTrait
    {
        public function batchCancelled()
        {
            return false;
        }
    }
}
