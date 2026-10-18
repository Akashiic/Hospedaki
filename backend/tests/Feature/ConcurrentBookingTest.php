<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Property;
use App\Services\BookingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pedidos simultâneos para as mesmas datas, em processos separados e num arquivo
 * SQLite de verdade (o banco em memória dos outros testes não tem concorrência).
 */
class ConcurrentBookingTest extends TestCase
{
    private const PROCESSES = 6;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('Precisa das extensões pcntl e posix.');
        }

        $this->dir = sys_get_temp_dir().'/hospedaki-concorrencia-'.uniqid();
        mkdir($this->dir);
        touch($this->dir.'/database.sqlite');

        config(['database.connections.sqlite.database' => $this->dir.'/database.sqlite']);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_simultaneous_requests_for_the_same_dates_get_one_booking_and_clean_422s(): void
    {
        Notification::fake();
        $property = Property::factory()->create();
        $accommodation = Accommodation::factory()->for($property)->create(['max_guests' => 4]);
        $data = [
            'accommodation_ids' => [$accommodation->id],
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => 2,
            'guest_name' => 'Maria',
            'guest_phone' => '11999999999',
        ];

        // Nenhum processo filho pode herdar a conexão aberta do pai.
        DB::disconnect('sqlite');
        $start = microtime(true) + 0.3;
        $pids = [];

        for ($i = 0; $i < self::PROCESSES; $i++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runChild($i, $start, $property, $data);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = array_map(
            fn (int $i) => (string) @file_get_contents("{$this->dir}/resultado-{$i}"),
            range(0, self::PROCESSES - 1),
        );

        $this->assertSame(1, count(array_keys($results, 'criado', true)), implode("\n", $results));
        $this->assertSame(self::PROCESSES - 1, count(array_keys($results, 'indisponivel', true)), implode("\n", $results));
        $this->assertSame(1, $property->bookings()->count());
    }

    /**
     * Roda no processo filho: tenta reservar e grava o resultado num arquivo.
     * Termina com SIGKILL para não rodar os handlers de encerramento do PHPUnit.
     */
    private function runChild(int $i, float $start, Property $property, array $data): never
    {
        try {
            DB::purge('sqlite');
            usleep(max(0, (int) (($start - microtime(true)) * 1_000_000)));
            app(BookingService::class)->request($property, $data);
            $result = 'criado';
        } catch (ValidationException) {
            $result = 'indisponivel';
        } catch (\Throwable $e) {
            $result = 'erro: '.$e->getMessage();
        }

        file_put_contents("{$this->dir}/resultado-{$i}", $result);
        posix_kill(getmypid(), SIGKILL);
        exit(1);
    }
}
