/**
 * AJAX handler for job filtering and pagination
 * Path: wp-content/plugins/origin-recruitment-utilities/assets/js/jobs-filter.js
 */
document.addEventListener("DOMContentLoaded", () => {
	const form = document.getElementById("jobs-filter-form");
	const resultsContainer = document.getElementById("jobs-results");
	const jobsColumn = document.getElementById("jobs-column");
	let isRequestInProgress = false;

	// Debounce function
	function debounce(fn, wait) {
		let timeout;
		return (...args) => {
			clearTimeout(timeout);
			timeout = setTimeout(() => fn(...args), wait);
		};
	}

	// Get query parameters from form and URL
	function getQueryParams(isInitialLoad = false) {
		const queryParams = {};

		// Merge with form data
		if (form) {
			const formData = new FormData(form);
			const formValues = {};
			for (const [name, value] of formData) {
				if (!value) continue;
				let key = name.endsWith("[]") ? name.slice(0, -2) : name;
				if (key === "location-filter") key = "country";
				if (key === "status-filter") key = "job_opening_status";
				if (name.endsWith("[]")) {
					if (!formValues[key]) formValues[key] = [];
					formValues[key].push(value);
				} else {
					formValues[key] = value;
				}
			}
			// Merge form values
			for (const [key, value] of Object.entries(formValues)) {
				queryParams[key] = key === "search" ? value : [...new Set(value)];
			}
		}
		// On initial load, check URL parameters for job_opening_status
		if (isInitialLoad) {
			const urlParams = new URLSearchParams(window.location.search);
			const status = urlParams.get("job_opening_status");
			if (status) {
				queryParams.job_opening_status = status.split(",").filter((v) => v);
			}
			console.log(status);
		}
		// Set job_opening_status to ["all"] if empty (only for non-initial load)
		if (!isInitialLoad && (!queryParams.job_opening_status || queryParams.job_opening_status.length === 0)) {
			queryParams.job_opening_status = ["all"];
		} else if (queryParams.job_opening_status && queryParams.job_opening_status.length === 1 && queryParams.job_opening_status[0] === "in-progress") {
			delete queryParams.job_opening_status;
		}
		console.debug("Query params:", queryParams);
		return queryParams;
	}

	// Debounced updateJobs to prevent rapid requests
	const debouncedUpdateJobs = debounce((page = 1, isUserInteraction = false) => {
		if (isRequestInProgress) {
			console.debug("Request in progress, skipping update");
			return;
		}

		if (!window.jobsFilter || !window.jobsFilter.ajaxurl || !window.jobsFilter.nonce || !window.jobsFilter.archiveurl) {
			console.error("jobsFilter config missing:", window.jobsFilter);
			resultsContainer.innerHTML = "<p>Error: Configuration missing.</p>";
			return;
		}

		isRequestInProgress = true;

		try {
			const queryParams = getQueryParams(!isUserInteraction);

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
			history.pushState({ page, queryParams }, "", newUrl);

			const data = new FormData();
			data.append("action", "filter_jobs");
			data.append("nonce", window.jobsFilter.nonce);
			data.append("page", page);
			data.append("query", JSON.stringify(queryParams));

			console.debug("Sending AJAX request:", { page, queryParams });

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
						// Scroll to top of #jobs-column for user interactions
						if (isUserInteraction && jobsColumn) {
							jobsColumn.scrollIntoView({ behavior: "smooth", block: "start" });
						}
					} else {
						throw new Error(json.data?.message || "Invalid response data");
					}
				})
				.catch((error) => {
					console.error("Fetch Error:", error.message, error.stack);
					resultsContainer.innerHTML = "<p>Error fetching jobs. Please try again.</p>";
				})
				.finally(() => {
					isRequestInProgress = false;
				});
		} catch (e) {
			console.error("debouncedUpdateJobs Error:", e.message, e.stack);
			resultsContainer.innerHTML = "<p>Error updating jobs. Please try again.</p>";
			isRequestInProgress = false;
		}
	}, 100);

	// Handle form submission
	if (form) {
		form.addEventListener("submit", (e) => {
			e.preventDefault();
			console.debug("Form submitted");
			debouncedUpdateJobs(1, true);
		});
		// Handle input changes for search
		const searchInput = document.getElementById("search-filter");
		if (searchInput) {
			searchInput.addEventListener(
				"input",
				debounce(() => {
					console.debug("Search input changed:", searchInput.value);
					debouncedUpdateJobs(1, true);
				}, 500)
			);
		}
	}

	// Handle pagination link clicks
	document.addEventListener("click", (e) => {
		const link = e.target.closest(".pagination a");
		if (link) {
			e.preventDefault();
			const href = link.getAttribute("href");
			const pageMatch = href.match(/page=(\d+)/);
			const page = pageMatch ? parseInt(pageMatch[1]) : 1;
			console.debug("Pagination clicked, page:", page);
			debouncedUpdateJobs(page, true);
		}
	});

	// Handle browser back/forward navigation
	window.addEventListener("popstate", (event) => {
		const page = event.state?.page || (new URLSearchParams(window.location.search).get("page") ? parseInt(new URLSearchParams(window.location.search).get("page")) : 1);
		console.debug("Popstate event, page:", page);
		debouncedUpdateJobs(page, false);
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
									const values = [...element.selectedOptions].map((opt) => opt.value);
									console.debug(`Select changed for ${selector}:`, values);
									debouncedUpdateJobs(1, true);
								}, 500)
							);

							// Handle tag removal
							element.parentElement.addEventListener("click", (event) => {
								const removeButton = event.target.closest("[data-remove]");
								if (removeButton) {
									event.preventDefault();
									console.debug(`Remove button clicked for ${selector}:`, removeButton);
									console.debug(`Parent elements:`, removeButton.closest(".hs-select"), removeButton.parentElement);

									const tagItem = removeButton.closest(".advanced-select__tag-item");
									if (!tagItem) {
										console.warn(`No tag item found for ${selector}`);
										return;
									}

									let tagText = "";
									const titleElement = tagItem.querySelector(".advanced-select__tag-item-title");
									tagText = titleElement ? titleElement.textContent.trim() : tagItem.textContent.trim();
									console.debug(`Removing tag for ${selector}:`, tagText);

									if (tagText) {
										const options = Array.from(element.options);
										const matchingOption = options.find((opt) => opt.textContent.trim().toLowerCase() === tagText.toLowerCase() || opt.value.toLowerCase() === tagText.toLowerCase());
										if (matchingOption) {
											console.debug(`Found matching option for ${selector}:`, matchingOption.value);
											matchingOption.selected = false;
											const selectedValues = [...element.selectedOptions].map((opt) => opt.value);
											console.debug(`Selected values after removal for ${selector}:`, selectedValues);
											debouncedUpdateJobs(1, true);
										} else {
											console.warn(`No matching option found for ${selector}:`, tagText);
										}
									} else {
										console.warn(`No valid tag text for ${selector}`);
									}
								}
							});
						} catch (e) {
							console.error(`HSSelect init failed for ${selector}:`, e.message, e.stack);
						}
					});

					// Trigger initial update on page load (no scroll)
					const urlParams = new URLSearchParams(window.location.search);
					const page = urlParams.get("page") ? parseInt(urlParams.get("page")) : 1;
					console.debug("Initial page load, page:", page);
					debouncedUpdateJobs(page, false);
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
						console.debug(`Native select changed:`, values);
						debouncedUpdateJobs(1, true);
					}
				});
			}
			// Trigger initial update for native selects (no scroll)
			const urlParams = new URLSearchParams(window.location.search);
			const page = urlParams.get("page") ? parseInt(urlParams.get("page")) : 1;
			console.debug("Initial native select load, page:", page);
			debouncedUpdateJobs(page, false);
		}

		tryInitialize();
	}

	initializeSelects();
});
