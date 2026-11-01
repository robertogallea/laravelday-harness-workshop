
// --- Suite di valutazione del docente (aggiunta da `score`, non fa parte del progetto) ---
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Eval');

pest()->in('Eval')->beforeEach(function () {
    $this->app->instance(App\Payments\PaymentGateway::class, $this->gateway = new Tests\Eval\Support\GatewaySpy);
});

require_once __DIR__.'/Eval/Support/helpers.php';
