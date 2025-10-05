from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Go to the login page
    page.goto("http://localhost/index.php")

    # Fill in the login form
    page.fill('input[name="username"]', "admin")
    page.fill('input[name="password"]', "dolibarr")
    page.click('input[type="submit"]')

    # Wait for navigation to the main page after login
    page.wait_for_url("**/index.php?mainmenu=home**")

    # Go to the customer return dashboard
    page.goto("http://localhost/custom/customerreturn/dashboard.php")

    # Wait for the charts to be rendered
    page.wait_for_selector("#returns-by-status-chart")
    page.wait_for_selector("#return-trends-chart")

    # Take a screenshot
    page.screenshot(path="jules-scratch/verification/dashboard_screenshot.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)