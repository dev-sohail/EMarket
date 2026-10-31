{{--
    Shared behaviour for the category create and edit forms.

    The editor script tag has to be present on both screens: the edit form used
    to call CKEDITOR.replace() without loading CKEDITOR at all, so the call
    threw a ReferenceError and the description stayed a plain textarea.
--}}
<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    const editor = document.getElementById('description');

    if (editor && window.CKEDITOR) {
        CKEDITOR.replace(editor);
    }
});
</script>