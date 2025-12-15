const { debug } = require("puppeteer");

// display error message
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

// Update Countdown
function updateCountdown(dueTime, offset) {
    let now = Date.now() + offset;
    let diff = dueTime - now;

    // Calculate remaining times
    let days = Math.floor(diff / (1000 * 60 * 60 * 24));
    let hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
    let minutes = Math.floor((diff / (1000 * 60)) % 60);
    let seconds = Math.floor((diff / 1000) % 60);

    // Update numbers
    document.getElementById("diff").textContent = diff;
    document.getElementById("cd-days").textContent = Math.max(days, 0);
    document.getElementById("cd-hours").textContent = hours.toString().padStart(2, '0');
    document.getElementById("cd-minutes").textContent = minutes.toString().padStart(2, '0');
    document.getElementById("cd-seconds").textContent = seconds.toString().padStart(2, '0');

    // Get all boxes
    let boxes = document.querySelectorAll(".countdown-box");

    // clear box styling
    boxes.forEach(box => {
        box.classList.remove("green", "yellow", "red", "flash");
    });

    // change the styling of the boxes based on time remaining 
    if (diff <= 0) {
        // Overdue
        boxes.forEach(box => box.classList.add("flash"));
        return;
    }

    if (diff < 0) {  
        // Past due
        boxes.forEach(box => box.classList.add("flash"));
    }
    else if (diff < (24*60*60)) {  
        // Due today
        boxes.forEach(box => box.classList.add("red"));
    }
    else if (diff < (7*24*60*60)) { 
        // Due within a week
        boxes.forEach(box => box.classList.add("yellow"));
    }
    else {
        // Due greater than a week
        boxes.forEach(box => box.classList.add("green"));
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const popup = document.querySelector(".popup.show");
    if (popup) {
         // hide popup 2 seconds after posting
        setTimeout(() => {
            popup.classList.add("hide");
        }, 2000);

        // remove popup completely after hiding 
        setTimeout(() => {
            popup.remove();
        }, 2600); 
    }
});

// ensure all data is present
function validateLogin() {
    const email = document.forms["loginForm"]["email"].value.trim();
    const password = document.forms["loginForm"]["password"].value.trim();

    let valid = true;

    document.getElementById("emailErr").innerText = "";
    document.getElementById("passwordErr").innerText = "";

    if (email === "") {
        document.getElementById("emailErr").innerText = "Email is required.";
        valid = false;
    }

    if (password === "") {
        document.getElementById("passwordErr").innerText = "Password is required.";
        valid = false;
    }

    return valid;
}

function escapeHtml(str) {
  return String(str ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
