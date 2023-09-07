const buttonTabMobile = document.querySelector('#admin_tab_mobile');

buttonTabMobile.addEventListener('click', el => {
    const content = document.querySelector('#admin-page-menu');
    if (content.style.maxHeight) {
        content.style.maxHeight = null;
    } else {
        content.style.maxHeight = content.scrollHeight + "px";
    }
})