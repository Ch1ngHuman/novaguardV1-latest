
const form = document.getElementById("form");
const input_firstName = document.getElementById("inputFirstName");
const input_email = document.getElementById("inputEmail");
const input_password = document.getElementById("inputPassword");
const input_repeatPassword = document.getElementById("inputRepeatPassword");
const error_Message = document.getElementById("errorMessage");


form.addEventListener("submit", (e) => {
    //e.preventDefault(); Prevent Submit

    let errors = []

    if (input_firstName) {
        //If we have a firstName input then we are in the signup page
        errors = getSignupFromErrors(input_firstName.value, input_email.value, input_password.value, input_repeatPassword.value) 
    } else {
        //If we don't have a firstName input then we are in the login page
        errors = getLoginFromErrors(input_email.value, input_password.value)
    }

    if (errors.length > 0) {
        //If there are any errors, prevent the form from submitting
        e.preventDefault()
        error_Message.innerText = errors.join(". ")
    }
})


function getSignupFromErrors(firstname, email, password, repeatPassword){

    let errors = []

    if (firstname === "" || firstname == null) {
        errors.push("Firstname is required")
        input_firstName.parentElement.classList.add("incorrect")
    }

    if (email === "" || email == null) {
        errors.push("Email is required")
        input_email.parentElement.classList.add("incorrect")
    }

    if (password === "" || password == null) {
        errors.push("Password is required")
        input_password.parentElement.classList.add("incorrect")
    }

    if(password.length < 8){
        errors.push("Password must have at least 8 characters")
        input_password.parentElement.classList.add("incorrect")
    }

    if (password !== repeatPassword || repeatPassword == null) {
        errors.push("Password does not match repeated password")
        input_password.parentElement.classList.add("incorrect")
        input_repeatPassword.parentElement.classList.add("incorrect")
    }
    

    return errors;
}

function getLoginFromErrors(email, password){
    let errors = []
    
    if (email === "" || email == null) {
        errors.push("Email is required")
        input_email.parentElement.classList.add("incorrect")
    }

    if (password === "" || password == null) {
        errors.push("Password is required")
        input_password.parentElement.classList.add("incorrect")
    }

    return errors;
}

function getForgotPasswordFromErrors(email){
    let errors = []

    if (email === "" || email == null) {
        errors.push("Email is required")
        input_email.parentElement.classList.add("incorrect")
    }

    return errors;
}

const allInputs = [input_firstName, input_email, input_password, input_repeatPassword].filter(input => input != null)

allInputs.forEach(input => {
    input.addEventListener("input", () => {
        if(input.parentElement.classList.contains("incorrect")){
            input.parentElement.classList.remove("incorrect")
            error_Message.innerText = ""
        }
    })
})

// --- From index.php ---
// Password eye toggle for login
function togglePassword(id, eyeId) {
    const input = document.getElementById(id);
    const eye = document.getElementById(eyeId);
    if (input.type === "password") {
        input.type = "text";
        eye.innerHTML = `<svg class='eye-icon' xmlns='http://www.w3.org/2000/svg' height='24px' viewBox='0 0 24 24' width='24px'><path d='M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z'/></svg>`;
    } else {
        input.type = "password";
        eye.innerHTML = `<svg class='eye-icon' xmlns='http://www.w3.org/2000/svg' height='24px' viewBox='0 0 24 24' width='24px'><path d='M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z'/><line x1='1' y1='1' x2='23' y2='23' stroke='black' stroke-width='2'/></svg>`;
    }
}

// Role selection functionality
function selectRole(role, el) {
    // Remove selected class from all options
    document.querySelectorAll('.role-option').forEach(option => {
        option.classList.remove('selected');
    });

    // Add selected class to clicked option if provided
    if (el && el.classList) {
        el.classList.add('selected');
    }

    // Redirect to login page after a short delay
    setTimeout(() => {
        if (role === 'guard') {
            window.location.href = './pages/login_guard.php';
        } else if (role === 'admin') {
            window.location.href = './pages/login_admin.php';
        }
    }, 300);
}