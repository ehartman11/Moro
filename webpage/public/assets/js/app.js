/**
 * Moro global front-end helpers.
 *
 * Rules:
 * - Must be safe to include on ANY page (no required DOM elements).
 * - Feature-specific code goes into assets/js/<feature>.js
 */

(function () {
  "use strict";

  // ---- HTML escape (safe for DOM insertion) ----
  window.escapeHtml = function (str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };

  // ---- Popup auto-dismiss (safe if popup not present) ----
  document.addEventListener("DOMContentLoaded", () => {
    const popup = document.querySelector(".popup.show");
    if (!popup) return;

    setTimeout(() => popup.classList.add("hide"), 2000);
    setTimeout(() => popup.remove(), 2600);
  });

  // ---- Optional: form helpers (only run when forms exist) ----
  window.printError = function (elemId, msg) {
    const el = document.getElementById(elemId);
    if (el) el.innerHTML = msg;
  };

  window.validateLogin = function () {
    const form = document.forms["loginForm"];
    if (!form) return true; // not on login page

    const email = (form["email"]?.value ?? "").trim();
    const password = (form["password"]?.value ?? "").trim();

    let valid = true;

    const emailErr = document.getElementById("emailErr");
    const passwordErr = document.getElementById("passwordErr");
    if (emailErr) emailErr.innerText = "";
    if (passwordErr) passwordErr.innerText = "";

    if (email === "") {
      if (emailErr) emailErr.innerText = "Email is required.";
      valid = false;
    }
    if (password === "") {
      if (passwordErr) passwordErr.innerText = "Password is required.";
      valid = false;
    }
    return valid;
  };
})();
