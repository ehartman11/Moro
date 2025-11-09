// display error message on php page 
function printError(elemId, msg) {
    document.getElementById(elemId).innerHTML = msg;
}

// this function will validate the user inputs in the form
function validateForm() {
    var fullName = document.appForm.fullName.value.trim();
    var birthdate = document.appForm.birthdate.value.trim();
    var email = document.appForm.email.value.trim();

    var isValid = true;

    // clear any previously displayed errors
    printError("fullNameErr", "");
    printError("birthdateErr", "");
    printError("emailErr", "");

    /* if any of the form entry boxes are blank, post the prescribed error message
        for the appropriate box(es) */
    if (fullName == "") {
        printError("fullNameErr", "Please enter your name.");
        isValid = false;
    }

    if (birthdate == "") {
        printError("birthdateErr", "Please enter your birthdate.");
        isValid = false;
    }

    if (email == "") {
        printError("emailErr", "Please enter your email.");
        isValid = false;
    }

    // if there are any invalid entries, return false (do not post)
    if (!isValid) return false;

    // If valid, show popup before submitting
    const popup = document.getElementById("successPopup");
    popup.classList.add("show");

    // Delay form submission so the popup is visible
    setTimeout(() => {
        document.appForm.submit();
    }, 1500);

    return false;
}
