    const header = document.querySelector('h1');
    console.log(header);
    if (header) {
        header.addEventListener('click', () => {
            header.style.color = 'red';
            alert('Kliknięto nagłówek!');
        });
    }