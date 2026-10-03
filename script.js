const form = document.getElementById('enrollment-form');
const statusMessage = document.getElementById('status-message');
const userIdInput = document.getElementById('user_id');
const firstNameInput = document.getElementById('first_name');
const lastNameInput = document.getElementById('last_name');
const listSearchInput = document.getElementById('list-search');
const customerRows = document.getElementById('customer-rows');
const pageInfo = document.getElementById('page-info');
const listState = {
    page: 1,
    perPage: 10,
    sort: 'user_id',
    direction: 'asc',
    totalPages: 0
};

function showStatus(message, state) {
    statusMessage.textContent = message;
    statusMessage.className = 'status status-' + state;
}

async function sendRequest(url, options) {
    const response = await fetch(url, options);
    let result;

    try {
        result = await response.json();
    } catch (error) {
        throw new Error('The server returned an unreadable response.');
    }

    if (!result || typeof result !== 'object' || !('success' in result)) {
        throw new Error('The server returned an unreadable response.');
    }

    if (!response.ok || !result.success) {
        const message = result.error && result.error.message
            ? result.error.message
            : 'The request could not be completed.';
        const error = new Error(message);
        error.status = response.status;
        throw error;
    }

    return result;
}

function customerPayload() {
    return {
        user_id: userIdInput.value.trim(),
        first_name: firstNameInput.value.trim(),
        last_name: lastNameInput.value.trim()
    };
}

function postJson(endpoint, payload) {
    return sendRequest(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
}

function setBusy(isBusy) {
    document.querySelectorAll('button').forEach(function (button) {
        button.disabled = isBusy;
    });

    if (!isBusy) {
        document.querySelector('[data-action="previous-page"]').disabled =
            listState.totalPages === 0 || listState.page <= 1;
        document.querySelector('[data-action="next-page"]').disabled =
            listState.totalPages === 0 || listState.page >= listState.totalPages;
    }
}

function renderCustomerRows(customers) {
    while (customerRows.firstChild) {
        customerRows.removeChild(customerRows.firstChild);
    }

    customers.forEach(function (customer) {
        const row = document.createElement('tr');
        const idCell = document.createElement('td');
        const firstNameCell = document.createElement('td');
        const lastNameCell = document.createElement('td');
        const actionCell = document.createElement('td');
        const loadButton = document.createElement('button');

        loadButton.type = 'button';
        loadButton.className = 'link-button';
        loadButton.dataset.customerId = customer.user_id;
        loadButton.textContent = 'Load';
        loadButton.setAttribute('aria-label', 'Load customer ' + customer.user_id + ' into the form');
        idCell.textContent = customer.user_id;
        firstNameCell.textContent = customer.first_name;
        lastNameCell.textContent = customer.last_name;
        actionCell.appendChild(loadButton);
        row.appendChild(idCell);
        row.appendChild(firstNameCell);
        row.appendChild(lastNameCell);
        row.appendChild(actionCell);
        customerRows.appendChild(row);
    });
}

async function loadCustomerList(page) {
    const query = new URLSearchParams({
        page: String(page),
        per_page: String(listState.perPage),
        search: listSearchInput.value.trim(),
        sort: listState.sort,
        direction: listState.direction
    });
    const result = await sendRequest('list_data.php?' + query.toString(), { method: 'GET' });
    const pagination = result.data.pagination;

    listState.page = pagination.page;
    listState.totalPages = pagination.total_pages;
    renderCustomerRows(result.data.customers);

    if (pagination.total === 0) {
        pageInfo.textContent = 'No customers found.';
    } else {
        pageInfo.textContent = 'Page ' + pagination.page + ' of ' + pagination.total_pages +
            ' (' + pagination.total + ' customers)';
    }

    document.querySelectorAll('th[aria-sort]').forEach(function (header) {
        const sortButton = header.querySelector('[data-sort]');
        if (sortButton.dataset.sort === listState.sort) {
            header.setAttribute('aria-sort', listState.direction === 'asc' ? 'ascending' : 'descending');
        } else {
            header.setAttribute('aria-sort', 'none');
        }
    });
    document.querySelector('[data-action="previous-page"]').disabled = pagination.page <= 1;
    document.querySelector('[data-action="next-page"]').disabled =
        pagination.total_pages === 0 || pagination.page >= pagination.total_pages;
}

function validateFields(fields) {
    for (const field of fields) {
        if (field.value.trim() === '') {
            field.value = field.value.trim();
            field.focus();
            field.reportValidity();
            return false;
        }
    }
    return true;
}

async function performAction(action) {
    const payload = customerPayload();

    if (action === 'search') {
        if (!validateFields([userIdInput])) {
            return;
        }

        const query = new URLSearchParams({ user_id: payload.user_id });
        try {
            const result = await sendRequest('get_data.php?' + query.toString(), { method: 'GET' });
            userIdInput.value = result.data.user_id;
            firstNameInput.value = result.data.first_name;
            lastNameInput.value = result.data.last_name;
            showStatus('Customer record found.', 'success');
        } catch (error) {
            if (error.status === 404) {
                firstNameInput.value = '';
                lastNameInput.value = '';
            }
            throw error;
        }
        return;
    }

    if (action === 'add' || action === 'update') {
        if (!validateFields([userIdInput, firstNameInput, lastNameInput])) {
            return;
        }

        const endpoint = action === 'add' ? 'insert_data.php' : 'update_data.php';
        const result = await postJson(endpoint, payload);
        showStatus(result.message, 'success');
        return;
    }

    if (action === 'list') {
        await loadCustomerList(1);
        showStatus('Customer list loaded.', 'success');
        return;
    }

    if (action === 'previous-page') {
        await loadCustomerList(Math.max(1, listState.page - 1));
        return;
    }

    if (action === 'next-page') {
        await loadCustomerList(Math.min(listState.totalPages, listState.page + 1));
        return;
    }

    if (action === 'delete') {
        if (!validateFields([userIdInput])) {
            return;
        }
        if (!window.confirm('Delete the customer with user ID "' + payload.user_id + '"?')) {
            return;
        }

        const result = await postJson('delete_data.php', { user_id: payload.user_id });
        firstNameInput.value = '';
        lastNameInput.value = '';
        showStatus(result.message, 'success');
    }
}

async function runAction(action) {
    setBusy(true);
    try {
        await performAction(action);
    } catch (error) {
        showStatus(error.message, 'error');
    } finally {
        setBusy(false);
    }
}

form.addEventListener('submit', function (event) {
    event.preventDefault();
    runAction('add');
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-action]');
    if (button && button.type !== 'submit') {
        runAction(button.dataset.action);
        return;
    }

    const sortButton = event.target.closest('[data-sort]');
    if (sortButton) {
        if (listState.sort === sortButton.dataset.sort) {
            listState.direction = listState.direction === 'asc' ? 'desc' : 'asc';
        } else {
            listState.sort = sortButton.dataset.sort;
            listState.direction = 'asc';
        }
        runAction('list');
        return;
    }

    const loadButton = event.target.closest('[data-customer-id]');
    if (loadButton) {
        userIdInput.value = loadButton.dataset.customerId;
        firstNameInput.value = loadButton.closest('tr').children[1].textContent;
        lastNameInput.value = loadButton.closest('tr').children[2].textContent;
        showStatus('Customer loaded into the form.', 'success');
    }
});

form.addEventListener('reset', function () {
    showStatus('', 'neutral');
});
