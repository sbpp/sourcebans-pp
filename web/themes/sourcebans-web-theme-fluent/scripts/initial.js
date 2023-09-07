const localStorageName = {
    color: 'sourcebans_fluent_color',
    dark: 'sourcebans_fluent_dark',
    manualDark: 'sourcebans_fluent_dark_manual'
};

// Check theme
if (localStorage.getItem(localStorageName.dark) == 1) {
    document.querySelector('html').dataset.theme = 'dark';
}