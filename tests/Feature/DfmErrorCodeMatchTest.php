<?php

use Tolery\AiCad\Livewire\Admin\ChatDetail;

/**
 * Couvre ChatDetail::matchDfmError() — le remplacement code DFM → message côté
 * admin, qui doit refléter checkDfmErrorCode() du front (chat-messages.blade.php).
 */
$codes = [
    '104.1' => 'Échec du serveur de génération de pièce — erreur d\'exécution.',
    '102.1' => 'Le serveur FreeCAD est actuellement indisponible.',
];

it('matche un contenu qui est exactement un code (Cas 1)', function () use ($codes) {
    expect(ChatDetail::matchDfmError('104.1', $codes))
        ->toBe(['code' => '104.1', 'message' => $codes['104.1']]);
});

it('matche un contenu exact entouré d\'espaces', function () use ($codes) {
    expect(ChatDetail::matchDfmError("  104.1\n", $codes))
        ->toBe(['code' => '104.1', 'message' => $codes['104.1']]);
});

it('matche un code noyé dans un texte plus long (Cas 2)', function () use ($codes) {
    expect(ChatDetail::matchDfmError("104.1\nVeuillez réessayer ultérieurement.", $codes))
        ->toBe(['code' => '104.1', 'message' => $codes['104.1']]);

    expect(ChatDetail::matchDfmError('Erreur lors de la génération : 104.1 — réessayez', $codes))
        ->toBe(['code' => '104.1', 'message' => $codes['104.1']]);
});

it('ne matche pas un code partiel (garde-fou anti 1104.10)', function () use ($codes) {
    expect(ChatDetail::matchDfmError('référence 1104.10 du lot', $codes))->toBeNull();
});

it('retourne null pour un texte sans code', function () use ($codes) {
    expect(ChatDetail::matchDfmError('Votre pièce a bien été générée.', $codes))->toBeNull();
});

it('retourne null pour un contenu vide ou null', function () use ($codes) {
    expect(ChatDetail::matchDfmError(null, $codes))->toBeNull();
    expect(ChatDetail::matchDfmError('', $codes))->toBeNull();
    expect(ChatDetail::matchDfmError('   ', $codes))->toBeNull();
});

it('retourne null quand la map de codes est vide', function () {
    expect(ChatDetail::matchDfmError('104.1', []))->toBeNull();
});
