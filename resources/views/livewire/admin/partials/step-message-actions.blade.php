<div class="flex items-center gap-2">
    <flux:button
        size="xs"
        variant="ghost"
        href="{{ route('ai-cad.admin.step-messages.edit', $stepMessage) }}"
        icon="pencil"
    >
        Modifier
    </flux:button>
    <form action="{{ route('ai-cad.admin.step-messages.destroy', $stepMessage) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message d\'étape ?')">
        @csrf
        @method('DELETE')
        <flux:button
            size="xs"
            variant="ghost"
            type="submit"
            icon="trash"
            class="text-red-600 hover:text-red-800"
        >
            Supprimer
        </flux:button>
    </form>
</div>
