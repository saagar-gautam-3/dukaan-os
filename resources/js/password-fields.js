const characterSets = {
    lowercase: 'abcdefghijkmnopqrstuvwxyz',
    uppercase: 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    digits: '23456789',
    symbols: '!@#$%^&*()-_=+[]{}:,.?',
};

const allCharacters = Object.values(characterSets).join('');
const uint32Range = 0x100000000;

function randomIndex(max) {
    const limit = uint32Range - (uint32Range % max);
    const sample = new Uint32Array(1);

    do {
        globalThis.crypto.getRandomValues(sample);
    } while (sample[0] >= limit);

    return sample[0] % max;
}

function generatePassword() {
    const characters = Object.values(characterSets).map((set) => set[randomIndex(set.length)]);

    while (characters.length < 14) {
        characters.push(allCharacters[randomIndex(allCharacters.length)]);
    }

    for (let index = characters.length - 1; index > 0; index -= 1) {
        const swapIndex = randomIndex(index + 1);
        [characters[index], characters[swapIndex]] = [characters[swapIndex], characters[index]];
    }

    return characters.join('');
}

function setVisibility(input, visible) {
    input.type = visible ? 'text' : 'password';

    const toggle = input.closest('[data-password-widget]')?.querySelector('[data-password-toggle]');
    if (!toggle) return;

    toggle.setAttribute('aria-pressed', String(visible));
    toggle.setAttribute('aria-label', visible ? toggle.dataset.hideLabel : toggle.dataset.showLabel);

    const icon = toggle.querySelector('.bi');
    icon?.classList.toggle('bi-eye', !visible);
    icon?.classList.toggle('bi-eye-slash', visible);
}

function passwordStrength(value) {
    const categories = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^A-Za-z0-9]/]
        .filter((pattern) => pattern.test(value)).length;

    if (value.length >= 14 && categories === 4) return 'strong';
    if (value.length >= 10 && categories >= 3) return 'medium';

    return 'weak';
}

function updateStrength(input) {
    const hint = input.closest('[data-password-widget]')?.querySelector('[data-password-strength]');
    if (!hint) return;

    if (!input.value) {
        hint.textContent = hint.dataset.hintLabel;
        hint.removeAttribute('data-strength');
        return;
    }

    const strength = passwordStrength(input.value);
    hint.textContent = hint.dataset[`${strength}Label`];
    hint.dataset.strength = strength;
}

function initializePasswordFields() {
    document.querySelectorAll('[data-password-widget]').forEach((widget) => {
        const input = widget.querySelector('[data-password-input]');
        if (!input) return;

        const toggle = widget.querySelector('[data-password-toggle]');
        toggle?.addEventListener('click', () => setVisibility(input, input.type === 'password'));
        input.addEventListener('input', () => updateStrength(input));

        const generateButton = widget.querySelector('[data-password-generate]');
        generateButton?.addEventListener('click', () => {
            if (typeof globalThis.crypto?.getRandomValues !== 'function') return;

            const password = generatePassword();
            input.value = password;
            setVisibility(input, true);
            updateStrength(input);

            const confirmationId = widget.dataset.passwordConfirmation;
            const confirmation = confirmationId ? document.getElementById(confirmationId) : null;
            if (confirmation instanceof HTMLInputElement) {
                confirmation.value = password;
                setVisibility(confirmation, true);
            }

            const generatedMessage = widget.querySelector('[data-password-generated]');
            if (generatedMessage) generatedMessage.textContent = generatedMessage.dataset.generatedLabel;
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePasswordFields, { once: true });
} else {
    initializePasswordFields();
}
