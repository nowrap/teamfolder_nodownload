document.addEventListener('DOMContentLoaded', () => {
	const save = document.getElementById('teamfolder-nodownload-save')
	if (!save) {
		return
	}
	const msg = document.getElementById('teamfolder-nodownload-msg')

	save.addEventListener('click', async () => {
		const blocked = {}
		document.querySelectorAll('#teamfolder-nodownload input[data-folder]:checked').forEach((box) => {
			(blocked[box.dataset.folder] ??= []).push(box.dataset.group)
		})
		msg.textContent = '…'
		const res = await fetch(OC.generateUrl('/apps/teamfolder_nodownload/settings'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
			body: JSON.stringify({ blocked }),
		})
		msg.textContent = res.ok ? t('teamfolder_nodownload', 'Saved') : t('teamfolder_nodownload', 'Error {status}', { status: res.status })
	})
})
