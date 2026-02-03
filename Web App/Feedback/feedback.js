document.getElementById("feedbackForm").addEventListener("submit", function (event) {
    event.preventDefault();

    const name = document.getElementById("name").value;
    const email = document.getElementById("email").value;
    const message = document.getElementById("message").value;


    if (name && email && message) {
        document.getElementById("thankYouMessage").style.display = "block";
        document.getElementById("feedbackForm").style.display = "none";
    } else {
        alert("Please fill out all the fields.");
    }
});