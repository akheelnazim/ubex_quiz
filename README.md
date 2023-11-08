# NPSC API

## Scenario

You are integrating UBEX's systems with NorthPole Shipping Company (NPSC), a fictional regional agent responsible for shipping goods between offshore oilfield workers and their families in eastern Europe and western Asia. NPSC's main office is located in Norway, and they expect to receive shipment data in their operating timezone, currently set as CEST (Central European Summer Time).

NPSC wants to display a week's worth of shipment data on their dashboard and has requested UBEX to provide an API endpoint to retrieve the latest statistics.

## Implementation

### Data Source

Assumptions:
- Data is stored in a database, to simulate a real world setting, accessible through the `connect.php` script.
- The database contains a table named "shipments" with shipment information from different vendors, in the format provided in the example data.
- The data provided is complete, i.e. contains the scheduling and every update of the shipments

### API Functionality

1. The API accepts a user-selected date and time in CEST via a `selected_date_time` parameter at endpoint `/ubex/api/npsc`. If not provided, it defaults to '2020-02-18 02:55:18' (latest entry in the example data).

2. It converts the selected date and time to GMT+3 using the `convertToGMT3` function, assuming that CEST corresponds to GMT+3.

3. The SQL query is formulated to retrieve shipment data for the last 7 days, including the day selected. It also filters by `agent` being 'NPSC'.

4. Dates are formatted as strings for the SQL queries.

5. The queries are prepared, and the parameters are bound and executed to retrieve the data.

6. The fetched data is processed to generate statistics using the `generateStats` function.
    - The total ongoing shipments before the first day which are calculated using their last updated date and status,    are added to the ongoing field for the first day.
    - Corresponding ongoing shipments are calculated cumulatively, decremented by 1 if it encounters a shipment of 'DELIVERED' or 'COMPLETED', otherwise incremented by 1.

7. The statistics are converted to the desired format, and the response is returned as a JSON object.

8. Exception handling is in place to capture errors and provide an error response.

Assumptions:
- The data for 7 days prior can be retrieved for any chosen date, not just the day of the request.
- The client selects the time in his/her timezone (CEST) which is then converted to GMT3 for calculations, to match the server timing.

### Code Improvements

To further enhance the solution:

- Implement authentication and authorization for API security.
- Document the API endpoints and usage to create clarity for the client.
- Implement validation for input data.
- Implement rate limiting to prevent abuse.
- Set up logging and error tracking.
- Conduct unit tests, integration tests, and end-to-end tests.
- Monitor the API's performance and health in real time.
- Plan for scalability with load balancing and horizontal scaling.
- Consider versioning for backward compatibility.
- Provide user feedback channels for improvements, and learning from the user.

### Testing Mechanisms

- Conduct manual testing of API endpoints using tools like Postman or web browsers.
- Write unit tests using a testing framework like PHPUnit.
- Perform integration and end-to-end testing with predefined test cases.
- Implement regression testing for code changes.
- Use load testing tools to simulate heavy traffic and assess performance.

### Alternative Data Formats

The code currently returns data in JSON format. Depending on client requirements, other formats like XML or CSV can be supported by adjusting the response format.

### Handling Frequent API Calls

If NPSC's system calls the API once every 500 milliseconds, it might lead to excessive load and potential issues. Implement rate limiting to restrict the number of requests from a single client within a specific time window. A healthy interval for polling depends on the specific use case, but it should be balanced to avoid overloading the API(for example 5-10 minutes).

### Caching

Caching can be applied to improve performance. Caching the results of database queries, especially for frequently accessed or relatively static data, can significantly reduce the load on the database, in this case, the shipping data for the last 7 days. Common caching mechanisms include in-memory caching or external caching solutions like Redis.

