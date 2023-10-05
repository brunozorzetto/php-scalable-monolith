<?php

namespace App\Jobs;

use App\Mail\SpecialistReport;
use App\Models\Specialist;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateHighestRatedSpecialistReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $userEmail)
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('GenerateHighestRatedSpecialistReportJob started...');
        try {
            $specialists = Specialist::select([
                'specialists.id',
                'specialists.name',
                DB::raw('avg(reviews.rating) avg_rating')
            ])
            ->join('reviews', 'reviews.specialist_id', '=', 'specialists.id')
            ->groupBy(['specialists.id', 'specialists.name'])
            ->orderBy('avg_rating', 'desc')
            ->limit(10)
            ->get();

            
            /**
             * @TODO - the best to do this is saving in a external storage, such as S3, Google Cloud Storage, etc.
             * It's just for a simple implementation to save a file in somewhere.
            */
            $fileName = 'highest_rated_specialists_' . time() . '.csv';
            $disk = Storage::disk('public');

            foreach ($specialists as $specialist) {
                $content = "{$specialist->id}, {$specialist->name}, {$specialist->avg_rating}";
                $disk->append($fileName, $content);
            }

            Mail::to($this->userEmail)->send(new SpecialistReport($disk->url($fileName)));
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        } finally {
            Log::info('GenerateHighestRatedSpecialistReportJob finished.');
        }
    }
}
