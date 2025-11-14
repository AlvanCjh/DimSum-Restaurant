// Dynamic sign in, login
const container = document.getElementById('container');
const signUpBtn = document.getElementById('signUpBtn');
const signInBtn = document.getElementById('signInBtn');

signUpBtn.addEventListener('click', () => {
    container.classList.add("right-panel-active");
});

signInBtn.addEventListener('click', () => {
    container.classList.remove("right-panel-active");
}); 

