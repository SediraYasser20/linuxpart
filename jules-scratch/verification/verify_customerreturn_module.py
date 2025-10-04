from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # 1. Go to login page
        page.goto("http://127.0.0.1:8080/index.php")

        # 2. Perform login
        page.locator('input[name="username"]').fill("admin")
        page.locator('input[name="password"]').fill("dolibarr")
        page.locator('input[type="submit"]').click()

        # 3. Wait for dashboard to load to confirm login
        expect(page).to_have_url("http://127.0.0.1:8080/index.php?mainmenu=home", timeout=10000)

        # 4. Navigate to the new customer return module list page
        page.goto("http://127.0.0.1:8080/custom/customerreturn/list.php")

        # 5. Assert that the page title is correct
        expect(page.locator('div.fiche-titre')).to_have_text("CustomerReturnsList")

        # 6. Take a screenshot of the list view
        page.screenshot(path="jules-scratch/verification/customer_return_list.png")
        print("Screenshot of list view taken successfully.")

        # 7. Navigate to the create new return page
        page.goto("http://127.0.0.1:8080/custom/customerreturn/card.php?action=create")

        # 8. Assert that the page title is correct for the create page
        expect(page.locator('div.fiche-titre')).to_have_text("NewCustomerReturn")

        # 9. Take a screenshot of the create view
        page.screenshot(path="jules-scratch/verification/customer_return_create.png")
        print("Screenshot of create view taken successfully.")


    except Exception as e:
        print(f"An error occurred: {e}")
        # Take a screenshot on error to help debug
        page.screenshot(path="jules-scratch/verification/error_screenshot.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)