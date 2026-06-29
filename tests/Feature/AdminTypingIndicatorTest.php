<?php

use Tolery\AiCad\Livewire\Admin\ChatDetail;

/**
 * Couvre ChatDetail::isTypingIndicator() — la détection du sentinelle interne
 * `[TYPING_INDICATOR]` que la vue admin doit intercepter pour afficher un
 * indicateur « génération en cours » au lieu d'afficher le marqueur brut (#2331).
 */
it('détecte le sentinelle de génération en vol', function () {
    expect(ChatDetail::isTypingIndicator('[TYPING_INDICATOR]'))->toBeTrue();
});

it('ne confond pas un vrai message avec le sentinelle', function () {
    expect(ChatDetail::isTypingIndicator('Votre pièce a bien été générée.'))->toBeFalse();
    expect(ChatDetail::isTypingIndicator('Voici comment je comprends votre demande'))->toBeFalse();
});

it('ne matche pas un sentinelle entouré de texte ou d\'espaces', function () {
    expect(ChatDetail::isTypingIndicator(' [TYPING_INDICATOR] '))->toBeFalse();
    expect(ChatDetail::isTypingIndicator('Erreur : [TYPING_INDICATOR]'))->toBeFalse();
});

it('retourne false pour un contenu vide ou null', function () {
    expect(ChatDetail::isTypingIndicator(null))->toBeFalse();
    expect(ChatDetail::isTypingIndicator(''))->toBeFalse();
});
