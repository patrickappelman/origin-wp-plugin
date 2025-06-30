document.addEventListener("DOMContentLoaded", function () {
	const nonceField = document.getElementById("application-nonce");
	if (nonceField && window.jobApplication && window.jobApplication.application_nonce) {
		nonceField.value = window.jobApplication.application_nonce;
	}

	const form = document.getElementById("oru-job-application-form");
	if (form) {
		const submitButton = form.querySelector('button[type="submit"]');
		form.addEventListener("submit", async function (event) {
			event.preventDefault();

			// Disable button and update text
			if (submitButton) {
				submitButton.disabled = true;
				const originalText = submitButton.innerHTML;
				const submittingText = submitButton.getAttribute("data-submitting-text") || "Submitting...";
				submitButton.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${submittingText}`;
			}

			try {
				const response = await fetch(window.jobApplication.ajaxurl, {
					method: "POST",
					body: new FormData(form),
				});

				if (!response.ok) {
					throw new Error("Network response was not ok");
				}

				const data = await response.json();

				if (data.success) {
					window.location.href = data.data.redirect_url;
				} else {
					throw new Error(data.data.message || "Unknown error");
				}
			} catch (error) {
				console.error("Fetch error:", error);
				alert("An error occurred. Please try again.");
			} finally {
				// Re-enable button and restore text
				if (submitButton) {
					submitButton.disabled = false;
					submitButton.innerHTML = originalText;
				}
			}
		});
	}
});
