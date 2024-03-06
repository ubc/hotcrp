<?php
// help/h_votetags.php -- HotCRP help functions
// Copyright (c) 2006-2022 Eddie Kohler; see LICENSE.

class VoteTags_HelpTopic {
    static function print(HelpRenderer $hth) {
        $votetag = $hth->example_tag(TagInfo::TF_ALLOTMENT) ?? "vote";
        echo "<p>Some programs have PC members vote for applications. In
<em>allotment voting</em>, each PC member is assigned a vote allotment to
distribute among unconflicted applications; a PC member might assign one vote to one
submission and five to another. In <em>approval voting</em>, each PC member
can vote for as many applications as they like. The PC’s aggregated vote totals
might help determine which applications to discuss.</p>

<p>HotCRP supports voting through ", $hth->help_link("tags", "tags"), ".
The chair can ", $hth->setting_link("define a set of voting tags", "tag_vote_allotment"),
" and allotments" . $hth->tag_settings_having_note(TagInfo::TF_ALLOTMENT) . ".
Votes are represented as twiddle tags, and the vote total is automatically
computed and shown in the public tag.</p>

<p>For example, an administrator might define an allotment voting tag
 “#". $votetag . "” with an allotment of 10 votes.
To assign two votes to a submission, a PC member can either enter that vote
into a text box on the submission page, or directly tag that submission with
“#~". $votetag . "#2”.
As other PC members add their votes with their own “#~vote” tags, the system
updates the main “#vote” tag to reflect the total.
(An error is reported when PC members exceed their allotment.) </p>

<p>To see applications’ vote counts in a list, search for ",
$hth->search_link("show:#{$votetag}"),
". To list the applications with votes, sorted by vote count (most votes first),
search for ", $hth->search_link("rorder:#{$votetag}"), " or ",
$hth->search_link("rorder:#{$votetag} show:#{$votetag}"), ".</p>

<p>Hover to learn how the PC voted:</p>

<p>" . Ht::img("extagvotehover.png", "[Hovering over a voting tag]", ["width" => 390, "height" => 46]) . "</p>";
    }
}
