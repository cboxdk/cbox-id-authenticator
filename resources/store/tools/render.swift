// Deterministic App Store composition. The source UI is unaltered and scaled as a whole.
// swift resources/store/tools/render.swift <source-dir> <output-dir> <copy-json> <app-icon>
import AppKit
import Foundation
let args = CommandLine.arguments
precondition(args.count == 5, "Expected source, output, copy JSON, app icon")
let source = args[1], output = args[2]
let copy = try JSONDecoder().decode([String: [[String]]].self, from: Data(contentsOf: URL(fileURLWithPath: args[3])))
let icon = NSImage(contentsOfFile: args[4])!
let fm = FileManager.default
let W: CGFloat = 1320, H: CGFloat = 2868
let inputs = ["01-requests", "03-codes-grid", "02-approval", "06-backup", "05-accounts", "04-codes-list"]
let names = ["01-approvals", "02-codes", "03-control", "04-backup", "05-accounts", "06-find"]
func color(_ h: UInt32) -> NSColor { NSColor(srgbRed: CGFloat((h >> 16)&255)/255, green: CGFloat((h >> 8)&255)/255, blue: CGFloat(h&255)/255, alpha: 1) }
let cream = color(0xF7F5F0), ink = color(0x202528), blue = color(0x0850AB), muted = color(0x59626F)
func box(_ x: CGFloat,_ top: CGFloat,_ w: CGFloat,_ h: CGFloat) -> NSRect { NSRect(x:x,y:H-top-h,width:w,height:h) }
func round(_ rect:NSRect,_ radius:CGFloat,_ fill:NSColor) { fill.setFill(); NSBezierPath(roundedRect:rect,xRadius:radius,yRadius:radius).fill() }
func text(_ string:String,top:CGFloat,size:CGFloat,weight:NSFont.Weight,color:NSColor,maxWidth:CGFloat=1160) {
    let paragraph=NSMutableParagraphStyle(); paragraph.alignment = .center
    var font=NSFont.systemFont(ofSize:size,weight:weight)
    while (string as NSString).size(withAttributes:[.font:font]).width > maxWidth && font.pointSize > 58 { font=NSFont.systemFont(ofSize:font.pointSize-1,weight:weight) }
    let attrs:[NSAttributedString.Key:Any] = [.font:font,.foregroundColor:color,.paragraphStyle:paragraph]
    (string as NSString).draw(in:box((W-maxWidth)/2,top,maxWidth,font.pointSize*1.3),withAttributes:attrs)
}
for locale in copy.keys.sorted() {
    let dir="\(output)/\(locale)";try fm.createDirectory(atPath:dir,withIntermediateDirectories:true)
    for i in 0..<6 {
        autoreleasepool {
            let bitmap=NSBitmapImageRep(bitmapDataPlanes:nil,pixelsWide:Int(W),pixelsHigh:Int(H),bitsPerSample:8,samplesPerPixel:4,hasAlpha:true,isPlanar:false,colorSpaceName:.deviceRGB,bytesPerRow:0,bitsPerPixel:0)!
            let context=NSGraphicsContext(bitmapImageRep:bitmap)!
            NSGraphicsContext.saveGraphicsState();NSGraphicsContext.current=context
            cream.setFill();NSRect(x:0,y:0,width:W,height:H).fill()
            // A quiet blue field anchors the phone while the warm top keeps the copy legible.
            color(0xE9EEF4).setFill(); NSBezierPath(ovalIn:box(-460,1150,2240,2300)).fill()
            blue.setFill();NSBezierPath(ovalIn:box(-520,1590,2360,2260)).fill()
            color(0x06418F).setFill();NSBezierPath(ovalIn:box(610,2130,1400,1450)).fill()
            // Actual app icon and native type, rather than a generated approximation.
            NSGraphicsContext.saveGraphicsState();NSBezierPath(roundedRect:box(501,86,70,70),xRadius:16,yRadius:16).addClip()
            icon.draw(in:box(501,86,70,70));NSGraphicsContext.restoreGraphicsState()
            let brand:[NSAttributedString.Key:Any] = [.font:NSFont.systemFont(ofSize:45,weight:.semibold),.foregroundColor:ink]
            ("Cbox ID" as NSString).draw(in:box(590,94,300,60),withAttributes:brand)
            let strings=copy[locale]![i]
            text(strings[0],top:235,size:106,weight:.bold,color:ink)
            text(strings[1],top:362,size:106,weight:.bold,color:blue)
            for (line,s) in strings[2].components(separatedBy:"\n").enumerated() {text(s,top:523+CGFloat(line)*54,size:40,weight:.regular,color:muted,maxWidth:1180)}
            let sw:CGFloat=930,sh:CGFloat=sw*2868/1320,x=(W-sw)/2,y:CGFloat=731
            let frame=box(x-18,y-18,sw+36,sh+36)
            NSGraphicsContext.saveGraphicsState()
            let shadow=NSShadow();shadow.shadowColor=NSColor.black.withAlphaComponent(0.25);shadow.shadowBlurRadius=55;shadow.shadowOffset=NSSize(width:0,height:-28);shadow.set()
            round(frame,106,color(0x17191E));NSGraphicsContext.restoreGraphicsState()
            let trim=NSBezierPath(roundedRect:frame.insetBy(dx:3,dy:3),xRadius:103,yRadius:103);trim.lineWidth=3;color(0x535965).setStroke();trim.stroke()
            NSGraphicsContext.saveGraphicsState();NSBezierPath(roundedRect:box(x,y,sw,sh),xRadius:90,yRadius:90).addClip()
            let ui=NSImage(contentsOfFile:"\(source)/\(locale)-\(inputs[i]).png")!
            context.imageInterpolation = .high;ui.draw(in:box(x,y,sw,sh))
            NSGraphicsContext.restoreGraphicsState()
            // Device chrome, outside app content; the simulator capture has no camera island.
            round(box(W/2-106,y+20,212,62),31,color(0x08090B))
            round(box(x-24,y+310,7,100),3,color(0x414650))
            round(box(x-24,y+444,7,160),3,color(0x414650))
            round(box(x+sw+17,y+416,7,200),3,color(0x414650))
            NSGraphicsContext.restoreGraphicsState()
            let png=bitmap.representation(using:.jpeg,properties:[.compressionFactor:0.98])!
            try! png.write(to:URL(fileURLWithPath:"\(dir)/\(names[i]).jpg"))
        }
    }
    print("Rendered \(locale): 6 images, 1320×2868 RGB JPEG")
}
