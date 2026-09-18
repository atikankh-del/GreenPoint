<?php
// Integration check using two independent PHP processes and a disposable SQLite DB.
$root=dirname(__DIR__);
$database=$root.'/storage/framework/testing/concurrency.sqlite';
if(!is_dir(dirname($database))) mkdir(dirname($database),0777,true);
$worker=($argv[1]??'')==='worker';
if(!$worker && file_exists($database)) throw new RuntimeException('Test database already exists; inspect it before rerunning.');
if(!$worker) touch($database);
foreach(['DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'SESSION_DRIVER'=>'array','CACHE_STORE'=>'array'] as $key=>$value){putenv("$key=$value");$_ENV[$key]=$_SERVER[$key]=$value;}
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if($worker){
    Illuminate\Support\Facades\Auth::login(App\Models\User::firstOrFail());
    $reward=App\Models\Reward::firstOrFail();
    while(microtime(true)<(float)$argv[2]) usleep(1000);
    try {app(App\Http\Controllers\GreenPointController::class)->redeem($reward);echo 'success';}
    catch(Illuminate\Validation\ValidationException $e){echo 'rejected';}
    exit;
}
try {
    Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
    App\Models\User::create(['name'=>'Concurrency','email'=>'concurrency@example.test','password'=>'password','points'=>100]);
    App\Models\Reward::create(['name'=>'Last item','description'=>'Test','points_required'=>60,'stock'=>1,'status'=>'active']);
    $jobs=[];$start=microtime(true)+2;
    for($i=0;$i<2;$i++){
        $process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        fclose($pipes[0]);$jobs[]=[$process,$pipes];
    }
    $results=[];
    foreach($jobs as [$process,$pipes]){$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);if($code!==0)throw new RuntimeException($output.$errors);$results[]=trim($output);}
    sort($results);
    if($results!==['rejected','success']||App\Models\User::first()->points!==40||App\Models\Reward::first()->stock!==0||App\Models\RewardRedemption::count()!==1)throw new RuntimeException('Concurrency assertion failed: '.json_encode($results));
    echo "PASS: concurrent requests produced one redemption, balance 40, stock 0.\n";
} finally {Illuminate\Support\Facades\DB::disconnect();if(file_exists($database))unlink($database);}
