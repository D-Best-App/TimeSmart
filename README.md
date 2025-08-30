# D-BEST TimeSmart

D-BEST TimeSmart is a web-based time clock application designed to manage employee time attendance records efficiently. It provides functionalities for employees to clock in/out and for administrators to manage users, view reports, and export data.

## Features

*   **Employee Time Tracking:** Clock-in and clock-out functionality.
*   **User Management:** Add, edit, and manage employee accounts.
*   **Admin Dashboard:** Overview of time clock activities.
*   **Reporting:** Generate attendance reports, summaries, and export data (Excel, PDF).
*   **Two-Factor Authentication (2FA):** Enhanced security for user logins.
*   **Privacy Policy & Terms of Use:** Dedicated pages for legal information.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

*   **Docker:** For running the application in a container.
*   **Git:** For cloning the repository.
*   **MySQL Client:** For creating the database.

## Installation

Follow these steps to set up D-BEST TimeSmart using the installation script:

1.  **Run the installation script:**

    ```bash
    bash <(curl -s https://raw.githubusercontent.com/D-Best-App/Timesmart/main/Install/install.sh)
    ```

2.  **Follow the on-screen prompts:**

    The script will guide you through the following steps:
    *   **Enter the company name:** This will be used to name the Docker container and the database.
    *   **Enter database credentials:** You will be prompted to enter the database host, user, and password.
    *   **Create Docker container:** The script will ask for confirmation to create the Docker container.
    *   **Create database:** The script will ask for confirmation to create the database.

3.  **Access the Application:**

    Once the installation is complete, you can access the application. Since the container is running in bridge mode and the port is not exposed, you will need to find the IP address of the container to access it.

    You can find the IP address of the container by running the following command:

    ```bash
    docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' <container_name>
    ```

    Replace `<container_name>` with the name of the container (e.g., `Timeclock-YourCompanyName`).

    Open your web browser and navigate to `http://<container_ip_address>`.

## Usage

*   **Admin Login:**
    *   Navigate to `http://<container_ip_address>/admin/login.php` to access the admin login page.
    *   The default admin credentials are:
        *   **Username:** admin
        *   **Password:** password
*   **Employee Login:**
    *   Navigate to `http://<container_ip_address>/user/login.php` to access the employee login page.
    *   Employees can log in with the credentials created by the administrator.

## Troubleshooting

*   **`docker: command not found`:** Ensure that you have Docker installed correctly.
*   **`git: command not found`:** Ensure that you have Git installed correctly.
*   **`mysql: command not found`:** Ensure that you have the MySQL client installed correctly.
*   **Database Connection Error:** Double-check your database credentials during the installation process.

## Contributing

Contributions are welcome! Please feel free to fork the repository, make your changes, and submit a pull request.

