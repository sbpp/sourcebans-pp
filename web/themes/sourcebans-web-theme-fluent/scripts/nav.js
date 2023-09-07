const button_mobile_open = document.querySelector('#button_mobile_open');
const button_mobile_close = document.querySelector('#button_mobile_close');
const mobile = document.querySelector('#layout_mobile');

button_mobile_open.addEventListener('click', () => {
    document.body.style.overflow = 'hidden';
    mobile.classList.add('show');
});

mobile.addEventListener('click', () => {
    document.body.style.overflow = 'auto';
    mobile.classList.remove('show');
})

document.querySelector('.nav_mobile_content').addEventListener('click', el => el.stopPropagation());