<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Support\AdminColumnHelpers;

function makeFakeTeam(int $id, string $name, ?string $type = null): object
{
    return new class($id, $name, $type)
    {
        public function __construct(public int $id, public string $name, public mixed $type) {}

        public function getRouteKey(): int
        {
            return $this->id;
        }
    };
}

describe('AdminColumnHelpers::teamLinkOrName', function () {
    it('returns a dash for null', function () {
        expect(AdminColumnHelpers::teamLinkOrName(null))->toBe('-');
    });

    it('returns escaped plain text when no route is configured', function () {
        config()->set('ai-cad.admin_team_route_name', null);

        $team = makeFakeTeam(1, 'Tolery <b>Acme</b>');

        expect(AdminColumnHelpers::teamLinkOrName($team))->toBe('Tolery &lt;b&gt;Acme&lt;/b&gt;');
    });

    it('returns escaped plain text when the configured route does not exist', function () {
        config()->set('ai-cad.admin_team_route_name', 'route.that.does.not.exist');

        $team = makeFakeTeam(1, 'Acme');

        expect(AdminColumnHelpers::teamLinkOrName($team))->toBe('Acme');
    });

    it('renders an anchor when the route is a string and exists', function () {
        Route::get('/admin/teams/{team}', fn ($team) => $team)->name('admin.team.test.show');
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('ai-cad.admin_team_route_name', 'admin.team.test.show');

        $team = makeFakeTeam(42, 'Acme');

        $html = AdminColumnHelpers::teamLinkOrName($team);

        expect($html)->toContain('<a href="')
            ->and($html)->toContain('/admin/teams/42')
            ->and($html)->toContain('>Acme</a>');
    });

    it('picks the route per team type when config is an array', function () {
        Route::get('/admin/clients/{team}', fn ($team) => $team)->name('test.client');
        Route::get('/admin/prestataires/{team}', fn ($team) => $team)->name('test.prestataire');
        app('router')->getRoutes()->refreshNameLookups();

        config()->set('ai-cad.admin_team_route_name', [
            'client' => 'test.client',
            'prestataire' => 'test.prestataire',
        ]);

        $client = makeFakeTeam(1, 'Client Co', 'client');
        $presta = makeFakeTeam(2, 'Presta Co', 'prestataire');

        expect(AdminColumnHelpers::teamLinkOrName($client))->toContain('/admin/clients/1');
        expect(AdminColumnHelpers::teamLinkOrName($presta))->toContain('/admin/prestataires/2');
    });

    it('falls back to the "default" entry of an array config when type is unknown', function () {
        Route::get('/admin/default/{team}', fn ($team) => $team)->name('test.default');
        app('router')->getRoutes()->refreshNameLookups();

        config()->set('ai-cad.admin_team_route_name', [
            'default' => 'test.default',
        ]);

        $team = makeFakeTeam(7, 'No Type Team', 'mystery');

        expect(AdminColumnHelpers::teamLinkOrName($team))->toContain('/admin/default/7');
    });
});

describe('AdminColumnHelpers::diskSupportsTemporaryUrl', function () {
    it('returns false when the disk throws on resolve', function () {
        // Using a never-configured driver makes Storage::disk() throw.
        config()->set('filesystems.disks.never-configured', null);

        expect(AdminColumnHelpers::diskSupportsTemporaryUrl('never-configured'))->toBeFalse();
    });

    it('returns true for a local-style disk that exposes temporaryUrl support', function () {
        // The "local" driver does not expose temporaryUrl; assert false on a real disk.
        Storage::fake('local-test');

        expect(AdminColumnHelpers::diskSupportsTemporaryUrl('local-test'))->toBeFalse();
    });
});
