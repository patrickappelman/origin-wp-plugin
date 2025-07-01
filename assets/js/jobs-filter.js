/**
 * AJAX handler for job filtering and pagination
 * Path: wp-content/plugins/origin-recruitment-utilities/assets/js/jobs-filter.js
 */
document.addEventListener("DOMContentLoaded", () => {
	const form = document.getElementById("jobs-filter-form");
	const resultsContainer = document.getElementById("jobs-results");
	let isRequestInProgress = false;

	// Create a single IntersectionObserver instance
	const fadeUpObserver = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					entry.target.classList.add("fade-up--faded");
					fadeUpObserver.unobserve(entry.target);
				}
			});
		},
		{
			threshold: 0.15,
		}
	);

	// Function to apply fade-up animation to elements
	function applyFadeUpAnimations(elements) {
		const viewportHeight = window.innerHeight;
		elements.forEach((el) => {
			const rect = el.getBoundingClientRect();
			if (rect.top < viewportHeight && rect.bottom > 0) {
				el.classList.add("fade-up--faded");
			} else {
				fadeUpObserver.observe(el);
			}
		});
	}

	// Debounce function
	function debounce(fn, wait) {
		let timeout;
		return (...args) => {
			clearTimeout(timeout);
			timeout = setTimeout(() => fn(...args), wait);
		};
	}

	// Get query parameters from form or URL
	function getQueryParams() {
		const queryParams = {};
		// Get URL parameters
		const urlParams = new URLSearchParams(window.location.search);
		urlParams.forEach((value, key) => {
			if (key === "page") return; // Skip page parameter
			if (queryParams[key]) {
				if (!Array.isArray(queryParams[key])) {
					queryParams[key] = [queryParams[key]];
				}
				queryParams[key].push(value);
			} else {
				queryParams[key] = value;
			}
		});
		// Merge with form data
		if (form) {
			const formData = new FormData(form);
			for (const [name, value] of formData) {
				if (!value) continue;
				let key = name.endsWith("[]") ? name.slice(0, -2) : name;
				if (key === "location-filter") key = "country";
				if (key === "status-filter") key = "job_opening_status";
				if (name.endsWith("[]")) {
					if (!queryParams[key]) queryParams[key] = [];
					queryParams[key].push(value);
				} else {
					queryParams[key] = value;
				}
			}
		}
		// Handle job_opening_status specially
		if (queryParams.job_opening_status && queryParams.job_opening_status.includes("in-progress")) {
			delete queryParams.job_opening_status; // Omit if default
		}
		return queryParams;
	}

	// Debounced updateJobs for filtering and pagination
	const debouncedUpdateJobs = debounce((page = 1) => {
		if (isRequestInProgress) {
			return;
		}

		if (!window.jobsFilter || !window.jobsFilter.ajaxurl || !window.jobsFilter.nonce || !window.jobsFilter.archiveurl) {
			console.error("jobsFilter config missing:", window.jobsFilter);
			resultsContainer.innerHTML = "<p>Error: Configuration missing.</p>";
			applyFadeUpAnimations([resultsContainer.querySelector("p")]);
			return;
		}

		isRequestInProgress = true;
		const queryParams = getQueryParams();

		// Construct query string for URL
		const queryStringParts = [];
		for (const [key, value] of Object.entries(queryParams)) {
			const encodedKey = encodeURIComponent(key);
			const encodedValue = Array.isArray(value) ? value.map(encodeURIComponent).join(",") : encodeURIComponent(value);
			queryStringParts.push(`${encodedKey}=${encodedValue}`);
		}
		if (page > 1) {
			queryStringParts.push(`page=${encodeURIComponent(page)}`);
		}
		const queryString = queryStringParts.join("&");
		const newUrl = `${window.jobsFilter.archiveurl}${queryString ? "?" + queryString : ""}`;
		history.pushState({}, "", newUrl);

		const data = new FormData();
		data.append("action", "filter_jobs");
		data.append("nonce", window.jobsFilter.nonce);
		data.append("page", page);
		data.append("query", JSON.stringify(queryParams));

		fetch(window.jobsFilter.ajaxurl, {
			method: "POST",
			body: data,
		})
			.then((response) => {
				if (!response.ok) {
					return response.text().then((text) => {
						throw new Error(`HTTP ${response.status}: ${text}`);
					});
				}
				return response.text();
			})
			.then((text) => {
				if (!text) {
					throw new Error("Empty response");
				}
				const json = JSON.parse(text);
				if (json.success && typeof json.data === "string") {
					resultsContainer.innerHTML = json.data;
					const newJobListings = resultsContainer.querySelectorAll(".job-listing.fade-up");
					applyFadeUpAnimations(newJobListings);
				} else {
					throw new Error(json.data?.message || "Invalid response data");
				}
			})
			.catch((error) => {
				console.error("Fetch Error:", error.message, error.stack);
				resultsContainer.innerHTML = "<p>Error fetching jobs. Please try again.</p>";
				const errorElement = resultsContainer.querySelector("p");
				if (errorElement) {
					errorElement.classList.add("fade-up");
					applyFadeUpAnimations([errorElement]);
				}
			})
			.finally(() => {
				isRequestInProgress = false;
			});
	}, 100);

	// Handle form submission
	if (form) {
		form.addEventListener("submit", (e) => {
			e.preventDefault();
			debouncedUpdateJobs(1);
		});
	}

	// Handle pagination link clicks
	document.addEventListener("click", (e) => {
		const link = e.target.closest(".pagination a");
		if (link) {
			e.preventDefault();
			const href = link.getAttribute("href");
			const pageMatch = href.match(/page=(\d+)/);
			const page = pageMatch ? parseInt(pageMatch[1]) : 1;
			debouncedUpdateJobs(page);
		}
	});

	// Handle browser back/forward navigation
	window.addEventListener("popstate", () => {
		const urlParams = new URLSearchParams(window.location.search);
		const page = urlParams.get("page") ? parseInt(urlParams.get("page")) : 1;
		debouncedUpdateJobs(page);
	});

	// Initialize Preline Advanced Select
	const selectFields = ["#language-filter", "#location-filter", "#industry-filter", "#sector-filter", "#status-filter"];

	function initializeSelects() {
		if (!window.jobsFilter) {
			console.error("jobsFilter object not defined. Check wp_localize_script.");
			setupNativeSelects();
			return;
		}

		function isPrelineReady() {
			return window.HSSelect && typeof window.HSSelect === "function" && window.HSStaticMethods && typeof window.HSStaticMethods.autoInit === "function";
		}

		function tryInitialize(attempts = 10, delay = 100) {
			if (attempts <= 0) {
				console.warn("Preline failed to load after retries. Using native select.");
				setupNativeSelects();
				return;
			}

			if (isPrelineReady()) {
				try {
					window.HSStaticMethods.autoInit(["select"]);
					selectFields.forEach((selector) => {
						const element = document.querySelector(selector);
						if (!element) {
							console.warn(`Element not found: ${selector}`);
							return;
						}

						try {
							const hsSelect = window.HSSelect.getInstance(element) || new window.HSSelect(element);
							hsSelect.on(
								"change",
								debounce(() => {
									let value;
									try {
										value = hsSelect.getSelected ? hsSelect.getSelected() : [...element.selectedOptions].map((opt) => opt.value);
									} catch (e) {
										console.warn(`Failed to get value for ${selector}:`, e.message);
										value = [...element.selectedOptions].map((opt) => opt.value);
									}
									if (!value || (Array.isArray(value) && value.length === 0)) {
										console.warn(`No valid value for ${selector}`);
									}
									debouncedUpdateJobs(1);
								}, 500)
							);

							element.parentElement.addEventListener("click", (event) => {
								const removeButton = event.target.closest("[data-remove]");
								if (removeButton) {
									const tagElement = removeButton.closest(".hs-select-tag");
									let tagText = "";
									if (tagElement) {
										for (const node of tagElement.childNodes) {
											if (node.nodeType === Node.TEXT_NODE) {
												tagText += node.textContent.trim();
											}
										}
									}
									let valueToRemove = "";
									if (tagText) {
										const options = Array.from(element.options);
										const matchingOption = options.find((opt) => opt.textContent.trim().toLowerCase() === tagText.toLowerCase() || opt.value.toLowerCase() === tagText.toLowerCase());
										valueToRemove = matchingOption ? matchingOption.value : tagText.toLowerCase();
									}

									setTimeout(() => {
										if (valueToRemove) {
											Array.from(element.options).forEach((option) => {
												if (option.value.toLowerCase() === valueToRemove.toLowerCase()) {
													option.selected = false;
												}
											});
										}
										debouncedUpdateJobs(1);
									}, 300);
								}
							});
						} catch (e) {
							console.error(`HSSelect init failed for ${selector}:`, e.message, e.stack);
						}
					});

					// Trigger initial update on page load
					debouncedUpdateJobs(1);
				} catch (e) {
					console.error("Preline autoInit failed:", e.message, e.stack);
					setupNativeSelects();
				}
			} else {
				setTimeout(() => tryInitialize(attempts - 1, delay * 1.5), delay);
			}
		}

		function setupNativeSelects() {
			if (form) {
				form.addEventListener("change", (event) => {
					if (event.target.matches("select")) {
						const values = [...event.target.selectedOptions].map((opt) => opt.value);
						if (values.length === 0) {
							console.warn(`No valid value for ${event.target.id}`);
						}
						debouncedUpdateJobs(1);
					}
				});
			}
		}

		tryInitialize();
	}

	initializeSelects();
});
