document.querySelectorAll('.collapse').forEach(el => {
    el.addEventListener('click', () => {
        const content = el.nextElementSibling.querySelector('.collapse_content');
        if (content.style.maxHeight) {
            content.style.maxHeight = null;
        } else {
            content.style.maxHeight = `${content.scrollHeight}px`;
        }
    });
})