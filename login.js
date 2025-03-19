// Toggle between login and signup forms
const toggleBtns = document.querySelectorAll(".toggle-btn");
const forms = document.querySelectorAll(".form");

// Add a debounce function for validation
const debounce = (fn, delay) => {
  let timeoutId;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn(...args), delay);
  };
};

// Create error display function instead of using alerts
const showError = (input, message) => {
  const errorDiv = input.nextElementSibling?.classList.contains("error-message")
    ? input.nextElementSibling
    : (() => {
        const div = document.createElement("div");
        div.className = "error-message";
        div.style.color = "red";
        input.parentNode.insertBefore(div, input.nextSibling);
        return div;
      })();

  errorDiv.textContent = message;
  errorDiv.style.display = "block";
};

const clearError = (input) => {
  const errorDiv = input.nextElementSibling;
  if (errorDiv?.classList.contains("error-message")) {
    errorDiv.style.display = "none";
  }
};

// Updated live validation function
const liveValidateInput = (input, validateFn, errorMessage) => {
  const validateWithDebounce = debounce((value) => {
    if (validateFn(value)) {
      input.classList.remove("invalid");
      input.classList.add("valid");
      clearError(input);
    } else {
      input.classList.remove("valid");
      input.classList.add("invalid");
      showError(input, errorMessage);
    }
  }, 300);

  input.addEventListener("input", (e) => validateWithDebounce(e.target.value));
};

// Fix toggle functionality
toggleBtns.forEach((btn) => {
  btn.addEventListener("click", () => {
    // Remove active class from all buttons
    toggleBtns.forEach((b) => b.classList.remove("active"));
    btn.classList.add("active");

    // Handle form transitions
    const formType = btn.getAttribute("data-form");
    forms.forEach((form) => {
      if (form.classList.contains("active")) {
        form.classList.add("fade-out");
        setTimeout(() => {
          form.classList.remove("active", "fade-out");
        }, 300);
      }

      if (form.id === `${formType}-form`) {
        setTimeout(() => {
          form.classList.add("active", "slide-in");
          setTimeout(() => form.classList.remove("slide-in"), 300);
        }, 300);
      }
    });
  });
});

// Form submission handling
const loginForm = document.getElementById("login-form");
const signupForm = document.getElementById("signup-form");

// Validation Functions
const isNotEmpty = (value) => value.trim() !== "";
const isValidEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
const isValidPassword = (password) =>
  password.length >= 8 && /[A-Z]/.test(password) && /\d/.test(password);
const isValidName = (name) => {
  const trimmedName = name.trim();
  return (
    trimmedName !== "" &&
    trimmedName.length <= 50 &&
    /^[A-Za-z\s]+$/.test(trimmedName)
  );
};

// Mobile number validation
function validateMobile(input) {
  const mobileError = document.getElementById("mobile-error");
  const mobileRegex = /^[6-9]\d{9}$/; // Indian mobile number format
  const value = input.value.trim();

  // Remove any existing classes
  input.classList.remove("valid", "invalid");

  if (value === "") {
    mobileError.textContent = "Mobile number is required";
    input.classList.add("invalid");
    input.setCustomValidity("Mobile number is required");
  } else if (!mobileRegex.test(value)) {
    mobileError.textContent = "Please enter a valid 10-digit mobile number starting with 6-9";
    input.classList.add("invalid");
    input.setCustomValidity("Invalid mobile number");
  } else {
    mobileError.textContent = "";
    input.classList.add("valid");
    input.setCustomValidity("");
  }
}

// Add input masking for mobile number
document.getElementById("mobile").addEventListener("keypress", function (e) {
  const char = String.fromCharCode(e.which);
  if (!/[0-9]/.test(char) || (this.value.length === 0 && !/[6-9]/.test(char))) {
    e.preventDefault();
  }
});

// Prevent paste of invalid characters in mobile field
document.getElementById("mobile").addEventListener("paste", function (e) {
  e.preventDefault();
  const text = (e.originalEvent || e).clipboardData.getData("text/plain");
  if (/^[6-9]\d{0,9}$/.test(text)) {
    this.value = text;
    validateMobile(this);
  }
});

// Attach live validation
document.addEventListener("DOMContentLoaded", () => {
  // Login form inputs
  const loginEmail = document.querySelector("#login-form input[name='email']");
  const loginPassword = document.querySelector(
    "#login-form input[name='password']"
  );

  liveValidateInput(
    loginEmail,
    isValidEmail,
    "Please enter a valid email address."
  );
  liveValidateInput(
    loginPassword,
    isValidPassword,
    "Password must be at least 8 characters, include one uppercase letter and one number."
  );

  // Signup form inputs
  const signupName = document.querySelector("#signup-form input[name='name']");
  const signupEmail = document.querySelector(
    "#signup-form input[name='email']"
  );
  const signupPassword = document.querySelector(
    "#signup-form input[name='password']"
  );
  const signupConfirmPassword = document.querySelector(
    "#signup-form input[name='confirm_password']"
  );
  const signupMobile = document.querySelector("#signup-form input[name='mobile']");

  liveValidateInput(
    signupName,
    isValidName,
    "Name is required, must contain only letters and spaces, and cannot exceed 50 characters."
  );
  liveValidateInput(
    signupEmail,
    isValidEmail,
    "Please enter a valid email address."
  );
  liveValidateInput(
    signupPassword,
    isValidPassword,
    "Password must be at least 8 characters, include one uppercase letter and one number."
  );

  // Updated confirm password validation
  signupConfirmPassword.addEventListener("input", () => {
    if (
      signupPassword.value.trim() === signupConfirmPassword.value.trim() &&
      signupPassword.value.trim() !== ""
    ) {
      signupConfirmPassword.classList.remove("invalid");
      signupConfirmPassword.classList.add("valid");
      clearError(signupConfirmPassword);
    } else {
      signupConfirmPassword.classList.remove("valid");
      signupConfirmPassword.classList.add("invalid");
      showError(signupConfirmPassword, "Passwords do not match.");
    }
  });

  signupMobile.addEventListener("input", () => validateMobile(signupMobile));

  // Add input field animations
  const inputFields = document.querySelectorAll("input:not([type='checkbox'])");
  inputFields.forEach((input) => {
    input.addEventListener("focus", () => {
      input.classList.add("input-focus");
    });

    input.addEventListener("blur", () => {
      input.classList.remove("input-focus");
    });
  });
});
