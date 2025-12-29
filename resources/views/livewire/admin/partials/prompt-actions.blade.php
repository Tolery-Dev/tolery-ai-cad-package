<div class="flex items-center gap-2">
    <flux:button
        size="xs"
        variant="ghost"
        href="{{ route('ai-cad.admin.prompts.edit', $prompt) }}"
        icon="pencil"
    >
        Modifier
    </flux:button>
    <form action="{{ route('ai-cad.admin.prompts.destroy', $prompt) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce prompt ?')">
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
