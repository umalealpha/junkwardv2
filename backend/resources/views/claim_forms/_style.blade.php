{{-- House styling for both renders. Navy #0D1B2A + orange #F4A623.
     Self-contained: the PDF renderer has no external network, so no fonts,
     no images, no stylesheets are fetched. --}}
<style>
    *      { box-sizing: border-box; }
    body   { margin: 0; background: #f4f6f8; color: #0D1B2A;
             font: 13px/1.5 Arial, Helvetica, sans-serif; }
    .doc   { max-width: 780px; margin: 0 auto; background: #fff; padding: 0 0 26px; }
    .hdr   { background: #0D1B2A; color: #fff; padding: 18px 24px; }
    .brand { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #cfd6e0; }
    .ttl   { font-size: 19px; font-weight: bold; color: #F4A623; margin-top: 3px; }
    .ref   { font-size: 12px; color: #cfd6e0; margin-top: 2px; }

    .intro { margin: 18px 24px 0; padding: 12px 14px; background: #fff8ec;
             border-left: 3px solid #F4A623; font-size: 13px; }

    .sec   { margin: 22px 24px 8px; padding-bottom: 5px; font-size: 12px; font-weight: bold;
             text-transform: uppercase; letter-spacing: .06em;
             border-bottom: 1px solid #e4e7ec; }

    .grid  { margin: 0 24px; }
    .f     { display: block; padding: 6px 0; border-bottom: 1px dotted #e4e7ec; }
    .l     { display: inline-block; width: 210px; vertical-align: top; color: #55606e; font-size: 12px; }
    .v     { display: inline-block; width: calc(100% - 220px); vertical-align: top; font-weight: bold; }
    .in    { border: 0; border-bottom: 1px solid #cfd6e0; padding: 3px 2px; font: inherit;
             font-weight: bold; color: #0D1B2A; background: transparent; }
    .in:focus { outline: 0; border-bottom-color: #F4A623; background: #fffdf7; }

    .qs    { margin: 0 24px; }
    .q     { display: block; padding: 9px 0; border-bottom: 1px dotted #e4e7ec; }
    .ql    { display: block; color: #55606e; font-size: 12px; margin-bottom: 4px; }
    .req   { color: #c0392b; }
    .qa    { border-bottom: 1px solid #cfd6e0; height: 20px; }
    .qa.tall { height: 46px; }
    .q input, .q textarea, .q select {
             width: 100%; border: 1px solid #cfd6e0; border-radius: 4px;
             padding: 7px 9px; font: inherit; color: #0D1B2A; background: #fff; }
    .q input:focus, .q textarea:focus, .q select:focus {
             outline: 0; border-color: #F4A623; box-shadow: 0 0 0 3px rgba(244,166,35,.15); }

    .sign  { margin: 34px 24px 0; }
    .sl    { display: inline-block; width: 46%; margin-right: 3%; font-size: 11px; color: #55606e; }
    .sl span { display: block; border-bottom: 1px solid #0D1B2A; height: 30px; margin-bottom: 4px; }
    .foot  { margin: 26px 24px 0; padding-top: 10px; border-top: 1px solid #e4e7ec;
             font-size: 10px; color: #7b8494; }
</style>
