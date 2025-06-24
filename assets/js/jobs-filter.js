document.addEventListener("DOMContentLoaded", () => {
	const form = document.getElementById("jobs-filter-form");
	const resultsContainer = document.getElementById("jobs-results");
	let isRequestInProgress = false;

	// Debounce function
	function debounce(fn, wait) {
		let timeout;
		return (...args) => {
			clearTimeout(timeout);
			timeout = setTimeout(() => fn(...args), wait);
		};
	}

	// Initialize Preline Advanced Select
	const selectFields = ["#language-filter", "#location-filter", "#industry-filter", "#sector-filter", "#status-filter"];

	function initializeSelects() {
		if (!window.jobsFilter) {
			console.error("jobsFilter object not defined. Check wp_localize_script.");
			setupNativeSelects();
			return;
		}

		// Check Preline readiness
		function isPrelineReady() {
			return window.HSSelect && typeof window.HSSelect === "function" && window.HSStaticMethods && typeof window.HSStaticMethods.autoInit === "function";
		}

		// Delay initialization to ensure Preline is fully loaded
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

							// Handle tag removal via click on "x"
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
				} catch (e) {
					console.error("Preline autoInit failed:", e.message, e.stack);
					setupNativeSelects();
				}
			} else {
				setTimeout(() => tryInitialize(attempts - 1, delay * 1.5), delay);
			}
		}

		// Fallback to native select events
		function setupNativeSelects() {
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

		tryInitialize();
	}

	initializeSelects();

	// Debounced updateJobs to prevent rapid requests
	const debouncedUpdateJobs = debounce((page = 1) => {
		if (isRequestInProgress) {
			return;
		}

		if (!window.jobsFilter || !window.jobsFilter.ajaxurl || !window.jobsFilter.nonce) {
			console.error("jobsFilter config missing:", window.jobsFilter);
			return;
		}

		isRequestInProgress = true;

		try {
			const formData = new FormData(form);
			const queryParams = {};

			// Map form field names to expected AJAX handler keys
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

			// Handle job_opening_status specially
			if (!queryParams.job_opening_status) {
				queryParams.job_opening_status = ["all"];
			} else if (queryParams.job_opening_status.includes("in-progress")) {
				delete queryParams.job_opening_status; // Omit from URL and query
			}

			// Manually construct query string to avoid encoding commas
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
			const newUrl = `${window.location.pathname}${queryString ? "?" + queryString : ""}`;
			history.pushState({}, "", newUrl);

			const data = new FormData();
			data.append("action", "filter_jobs");
			data.append("nonce", window.jobsFilter.nonce);
			data.append("page", page);
			data.append("query", JSON.stringify(queryParams));

			Promise.resolve()
				.then(() =>
					fetch(window.jobsFilter.ajaxurl, {
						method: "POST",
						body: data,
					})
				)
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

	const searchInput = document.getElementById("search-filter");
	if (searchInput) {
		searchInput.addEventListener(
			"input",
			debounce(() => {
				if (!searchInput.value.trim()) {
					console.warn("Empty search input.");
				}
				debouncedUpdateJobs(1);
			}, 500)
		);
	}

	document.addEventListener("click", (event) => {
		const link = event.target.closest(".pagination a");
		if (link) {
			event.preventDefault();
			const pageMatch = link.href.match(/page=(\d+)/);
			const page = pageMatch ? parseInt(pageMatch[1], 10) : 1;
			debouncedUpdateJobs(page);
		}
	});
});
