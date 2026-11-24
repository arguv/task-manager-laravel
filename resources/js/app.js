import './bootstrap';
import Sortable from 'sortablejs';

const errorBox = document.getElementById('js-error');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
}

document.querySelectorAll('ul.sortable').forEach((list) => {
    Sortable.create(list, {
        handle: '.handle',
        draggable: 'li[data-id]',
        animation: 150,
        onEnd: async () => {
            const ids = [...list.querySelectorAll('li[data-id]')].map((li) => Number(li.dataset.id));

            try {
                const { data } = await window.axios.patch(
                    list.dataset.url,
                    { project_id: list.dataset.projectId ? Number(list.dataset.projectId) : null, ids },
                    { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } },
                );

                data.data.forEach(({ id, priority }) => {
                    list.querySelector(`li[data-id="${id}"] .priority`).textContent = `#${priority}`;
                });
            } catch (error) {
                showError(error.response?.data?.errors?.ids?.[0] ?? 'Could not save the new order. Reloading...');
                setTimeout(() => window.location.reload(), 1500);
            }
        },
    });
});
